<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use CannaRewards\Services\UserService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Auth Controller (V2 Compliant)
 * Handles user registration, login, password reset, and magic links.
 */
class AuthController {
    private $user_service;

    public function __construct() {
        $this->user_service = new UserService();
    }

    /**
     * Handles user registration via the v2 endpoint.
     * Delegates the core logic to the UserService.
     */
    public function register_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        try {
            $result = $this->user_service->create_user( $request->get_json_params() );
            return new WP_REST_Response( $result, 201 );
        } catch ( \Exception $e ) {
            return new WP_Error( 'registration_failed', $e->getMessage(), [ 'status' => $e->getCode() ?: 400 ] );
        }
    }

    /**
     * Handles user login by proxying to the JWT plugin via the v2 endpoint.
     */
    public function login_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';

        if ( empty( $email ) || empty( $password ) ) {
            return new WP_Error( 'missing_credentials', 'Email and password are required.', [ 'status' => 400 ] );
        }

        $internal_request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
        $internal_request->set_body_params(['username' => $email, 'password' => $password]);
        $response = rest_do_request($internal_request);

        if ( $response->is_error() ) {
            return new WP_Error('auth_failed', 'Invalid username or password.', ['status' => 403]);
        }
        
        return new WP_REST_Response( $response->get_data(), 200 );
    }

    // --- Legacy & Utility Auth Methods ---
    // These methods are kept from your original file for functionality like password reset.
    // They are now non-static and belong to the instantiated controller.

    public function request_password_reset(WP_REST_Request $request) {
        // This logic is simple and doesn't need a service yet. It can remain here.
        // ... (The full code from your original file is preserved below)
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

    public function perform_password_reset(WP_REST_Request $request) {
        // ... (The full code from your original file is preserved below)
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
}