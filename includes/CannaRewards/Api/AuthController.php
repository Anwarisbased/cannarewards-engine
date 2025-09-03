<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use CannaRewards\Services\ReferralService;
use CannaRewards\Services\CDPService;
use CannaRewards\Includes\Event;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles Authentication & Session API Endpoints
 */
class AuthController {

    public static function register_routes() {
        // This function will be populated when we build the v2 auth endpoints.
        // It's called by the API Manager but does nothing for now.
    }

    public static function register_user(WP_REST_Request $request) {
        if (!get_option('users_can_register')) {
            return new WP_Error('registration_disabled', 'User registration is currently disabled.', ['status' => 403]);
        }

        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $password = $params['password'] ?? '';
        $first_name = sanitize_text_field($params['firstName'] ?? '');

        if (empty($email) || empty($password) || !is_email($email)) {
            return new WP_Error('registration_failed_data', 'A valid email and password are required.', ['status' => 400]);
        }
        if (email_exists($email)) {
            return new WP_Error('registration_failed_exists', 'An account with that email already exists.', ['status' => 409]);
        }

        $user_id = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => $first_name,
            'last_name'  => sanitize_text_field($params['lastName'] ?? ''),
            'role'       => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed_wp_error', $user_id->get_error_message(), ['status' => 500]);
        }

        update_user_meta($user_id, 'phone_number', sanitize_text_field($params['phone'] ?? ''));
        update_user_meta($user_id, 'marketing_consent', !empty($params['agreedToMarketing']));
        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        
        $referral_service = new ReferralService();
        $referral_service->generate_code_for_new_user($user_id, $first_name);

        $referral_code = sanitize_text_field($params['referralCode'] ?? null);
        
        // Broadcast the 'user_created' event with all necessary context.
        Event::broadcast('user_created', [
            'user_id'       => $user_id,
            'referral_code' => $referral_code
        ]);

        // The CDP track can also become a listener, but for now we leave it here for simplicity.
        $cdp_service = new CDPService();
        $cdp_service->track($user_id, 'user_created', [
            'signup_method'      => 'password',
            'referral_code_used' => $referral_code
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Registration successful. Please log in.',
            'user_id' => $user_id
        ], 201);
    }

    public static function proxy_login(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';
        if (empty($email) || empty($password)) {
            return new WP_Error('missing_credentials', 'Email and password are required.', ['status' => 400]);
        }

        $internal_request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
        $internal_request->set_body_params(['username' => $email, 'password' => $password]);

        $response = rest_do_request($internal_request);
        $data = $response->get_data();

        if (isset($data['code']) && strpos($data['code'], 'jwt_auth_') !== false) {
            return new WP_Error('auth_failed', 'Invalid username or password.', ['status' => 403]);
        }

        if ($response->is_error()) {
            return $response;
        }
        
        return new WP_REST_Response($data, 200);
    }

    public static function request_password_reset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $success_response = new WP_REST_Response(['success' => true, 'message' => 'If an account with that email exists, a reset link has been sent.'], 200);
        if (!is_email($email) || !email_exists($email)) {
            return $success_response;
        }
        $user = get_user_by('email', $email);
        $token = get_password_reset_key($user);
        if (is_wp_error($token)) {
            return new WP_Error('token_generation_failed', 'Could not generate reset token.', ['status' => 500]);
        }
        $options = get_option('canna_rewards_options');
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url();
        $reset_link = "$base_url/reset-password?token=$token&email=" . rawurlencode($email);
        wp_mail($email, 'Your Password Reset Request', "Click to reset: $reset_link \n\nThis link expires in 1 hour.");
        return $success_response;
    }

    public static function perform_password_reset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $token = sanitize_text_field($params['token'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $password = $params['password'] ?? '';
        
        $user = check_password_reset_key($token, $email);
        if (is_wp_error($user)) {
             return new WP_Error('invalid_token', 'Your password reset token is invalid or has expired.', ['status' => 400]);
        }

        reset_password($user, $password);
        
        return new WP_REST_Response(['success' => true, 'message' => 'Password has been reset successfully. You can now log in.'], 200);
    }

    public static function request_magic_link(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $success_response = new WP_REST_Response(['success' => true, 'message' => 'If an account with that email exists, a login link has been sent.'], 200);

        if (!is_email($email) || !email_exists($email)) {
            return $success_response;
        }

        $user = get_user_by('email', $email);
        $token = bin2hex(random_bytes(32));
        $expiration = time() + (15 * MINUTE_IN_SECONDS);

        update_user_meta($user->ID, '_magic_login_token', $token);
        update_user_meta($user->ID, '_magic_login_expiration', $expiration);

        $options = get_option('canna_rewards_options');
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url();
        $magic_link = "$base_url/auth/magic-login?token=$token";

        wp_mail($email, 'Your Secure Login Link', "Click this link to log in: $magic_link \n\nThis link expires in 15 minutes.");
        return $success_response;
    }

    public static function validate_magic_link(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $token = sanitize_text_field($params['token'] ?? '');

        if (empty($token)) {
            return new WP_Error('invalid_token', 'No token provided.', ['status' => 400]);
        }
        
        $found_users = get_users([
            'meta_key'   => '_magic_login_token',
            'meta_value' => $token,
            'number'     => 1,
            'fields'     => 'ID',
        ]);
        $user_id = !empty($found_users) ? (int) $found_users[0] : 0;
        if (empty($user_id)) {
            return new WP_Error('invalid_token', 'Your login link is invalid or has been used.', ['status' => 400]);
        }

        $expiration = get_user_meta($user_id, '_magic_login_expiration', true);
        if (empty($expiration) || time() > $expiration) {
            delete_user_meta($user_id, '_magic_login_token');
            delete_user_meta($user_id, '_magic_login_expiration');
            return new WP_Error('expired_token', 'Your login link has expired. Please request a new one.', ['status' => 400]);
        }

        delete_user_meta($user_id, '_magic_login_token');
        delete_user_meta($user_id, '_magic_login_expiration');
        
        if (!defined('JWT_AUTH_SECRET_KEY') || !class_exists('Firebase\\JWT\\JWT')) {
            return new WP_Error('jwt_not_configured', 'JWT Authentication is not properly configured.', ['status' => 500]);
        }

        $user = get_userdata($user_id);
        $issued_at = time();
        $expiration_time = apply_filters('jwt_auth_expire', $issued_at + (DAY_IN_SECONDS * 7), $issued_at);

        $token_payload = [
            'iss'  => get_bloginfo('url'),
            'iat'  => $issued_at,
            'nbf'  => $issued_at,
            'exp'  => $expiration_time,
            'data' => ['user' => ['id' => $user->ID]],
        ];
        
        $token_payload = apply_filters('jwt_auth_token_before_sign', $token_payload, $user);
        $jwt = \Firebase\JWT\JWT::encode($token_payload, JWT_AUTH_SECRET_KEY, 'HS256');

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return new WP_REST_Response(['success' => true, 'token' => $jwt], 200);
    }
}