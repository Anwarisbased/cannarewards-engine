<?php
/**
 * Handles Authentication & Session API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Auth_Controller {

    /**
     * Registers all authentication-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_public = '__return_true';

        register_rest_route($base, '/claim-unauthenticated', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'claim_unauthenticated_user'],
            'permission_callback' => $permission_public
        ]);
        
        register_rest_route($base, '/register',            ['methods' => 'POST', 'callback' => [__CLASS__, 'register_user'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/login',               ['methods' => 'POST', 'callback' => [__CLASS__, 'proxy_login'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/password/request',    ['methods' => 'POST', 'callback' => [__CLASS__, 'request_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/password/reset',      ['methods' => 'POST', 'callback' => [__CLASS__, 'perform_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/request-magic-link',  ['methods' => 'POST', 'callback' => [__CLASS__, 'request_magic_link'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/validate-magic-link', ['methods' => 'POST', 'callback' => [__CLASS__, 'validate_magic_link'], 'permission_callback' => $permission_public]);
    }

    /**
     * Handles the "Claim First, Ship Later" acquisition funnel.
     * Creates a user, a zero-dollar order for the gift, and sends a magic link.
     */
    public static function claim_unauthenticated_user(WP_REST_Request $request) {
        global $wpdb;
        $params = $request->get_json_params();

        $email = sanitize_email($params['email'] ?? '');
        $code  = sanitize_text_field($params['code'] ?? '');
        $first_name = sanitize_text_field($params['firstName'] ?? '');
        $last_name = sanitize_text_field($params['lastName'] ?? '');
        $shipping = $params['shippingDetails'] ?? [];

        if (empty($email) || !is_email($email) || empty($code) || empty($first_name)) {
            return new WP_Error('missing_parameters', 'Required fields are missing or invalid.', ['status' => 400]);
        }
        if (email_exists($email)) {
            return new WP_Error('email_exists', 'An account with this email address already exists. Please log in.', ['status' => 409]);
        }

        $codes_table = $wpdb->prefix . 'canna_reward_codes';
        $code_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $codes_table WHERE code = %s", $code));

        if (!$code_data) {
            return new WP_Error('code_invalid', 'This code is invalid.', ['status' => 404]);
        }
        if ($code_data->is_used == 1) {
            return new WP_Error('code_already_used', 'This code has already been used.', ['status' => 400]);
        }
        
        $settings = get_option('canna_rewards_options');
        $gift_product_id = isset($settings['welcome_reward_product']) ? (int)$settings['welcome_reward_product'] : 0;

        if ($gift_product_id <= 0 || !($product = wc_get_product($gift_product_id))) {
            return new WP_Error('gift_not_configured', 'The welcome gift is not configured correctly.', ['status' => 500]);
        }

        $password = wp_generate_password(24);
        $user_id = wp_create_user($email, $password, $email);

        if (is_wp_error($user_id)) {
            return new WP_Error('user_creation_failed', $user_id->get_error_message(), ['status' => 500]);
        }

        wp_update_user(['ID' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name]);
        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        update_user_meta($user_id, '_has_completed_first_scan', true);
        update_user_meta($user_id, '_onboarding_quest_step', 2);

        $address_map = [
            'firstName' => 'shipping_first_name', 'lastName' => 'shipping_last_name',
            'address1' => 'shipping_address_1', 'city' => 'shipping_city',
            'state' => 'shipping_state', 'zip' => 'shipping_postcode'
        ];
        foreach ($address_map as $key => $wc_key) {
            if (isset($shipping[$key])) {
                update_user_meta($user_id, $wc_key, sanitize_text_field($shipping[$key]));
            }
        }
        
        try {
            $order = wc_create_order(['customer_id' => $user_id]);
            $order->add_product($product, 1);
            $order->set_address([
                'first_name' => $first_name, 'last_name' => $last_name, 'email' => $email,
                'address_1' => $shipping['address1'] ?? '', 'city' => $shipping['city'] ?? '',
                'state' => $shipping['state'] ?? '', 'postcode' => $shipping['zip'] ?? '', 'country' => 'US'
            ], 'shipping');
            $order->set_address([
                'first_name' => $first_name, 'last_name' => $last_name, 'email' => $email,
            ], 'billing');
            
            $order->set_total(0);
            $order->calculate_totals();
            $order->update_status('processing', 'Unauthenticated Welcome Gift Claim');
            $order->save();
        } catch (Exception $e) {
            wp_delete_user($user_id);
            return new WP_Error('order_creation_failed', 'Could not create the welcome gift order.', ['status' => 500]);
        }

        $wpdb->update($codes_table, ['is_used' => 1, 'user_id' => $user_id, 'claimed_at' => current_time('mysql')], ['id' => $code_data->id]);

        $token = bin2hex(random_bytes(32));
        $expiration = time() + (15 * MINUTE_IN_SECONDS);
        update_user_meta($user_id, '_magic_login_token', $token);
        update_user_meta($user_id, '_magic_login_expiration', $expiration);

        $base_url = !empty($settings['frontend_url']) ? rtrim($settings['frontend_url'], '/') : home_url();
        $magic_link = "$base_url/auth/magic-login?token=$token";

        wp_mail(
            $email,
            'Activate Your New Account!',
            "Welcome! Your free gift is on its way. Please click this secure link to log in and activate your account: $magic_link \n\nThis link expires in 15 minutes."
        );

        Canna_CDP_Handler::send_event($user_id, 'user_acquired', [
            'acquisition_method'  => 'scan_to_claim',
            'product_scanned_sku' => $code_data->sku,
            'welcome_gift_id'     => $product->get_id(),
            'welcome_gift_name'   => $product->get_name(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Your gift has been claimed and your account has been created. Please check your email to activate.'
        ], 201);
    }

    /**
     * Handles standard user registration, including referral attribution.
     */
    public static function register_user(WP_REST_Request $request) {
        if (!get_option('users_can_register')) {
            return new WP_Error('registration_disabled', 'User registration is currently disabled.', ['status' => 403]);
        }
        
        $params = $request->get_json_params();
        
        $email = sanitize_email($params['email'] ?? '');
        $username = sanitize_user($params['username'] ?? $email, true);
        $password = $params['password'] ?? '';
        $age_gate = isset($params['agreedToTerms']) ? (bool) $params['agreedToTerms'] : false;

        if (!$age_gate) {
            return new WP_Error('age_gate_required', 'You must certify that you are 21 years of age or older.', ['status' => 400]);
        }
        if (empty($username) || empty($password) || empty($email)) {
            return new WP_Error('registration_failed_empty', 'Username, password, and email are required.', ['status' => 400]);
        }
        if (strlen($password) < 6) {
            return new WP_Error('registration_failed_password', 'Password must be at least 6 characters long.', ['status' => 400]);
        }
        if (!is_email($email)) {
            return new WP_Error('registration_failed_email', 'Invalid email address.', ['status' => 400]);
        }
        if (username_exists($username)) {
            return new WP_Error('registration_failed_username_exists', 'An account with that username already exists.', ['status' => 409]);
        }
        if (email_exists($email)) {
            return new WP_Error('registration_failed_email_exists', 'An account with that email address already exists.', ['status' => 409]);
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'first_name' => sanitize_text_field($params['firstName'] ?? ''),
            'last_name'  => sanitize_text_field($params['lastName'] ?? ''),
            'role'       => 'subscriber'
        ]);

        if (is_wp_error($user_id)) {
            return new WP_Error('registration_failed_wp_error', $user_id->get_error_message(), ['status' => 500]);
        }

        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        update_user_meta($user_id, '_has_completed_first_scan', false);
        if (!empty($params['phone'])) {
            update_user_meta($user_id, 'phone_number', sanitize_text_field($params['phone']));
        }
        if (isset($params['agreedToMarketing'])) {
            update_user_meta($user_id, 'marketing_consent', (bool) $params['agreedToMarketing']);
        }
        
        global $wpdb;
        $base_code_name = !empty($params['firstName']) ? $params['firstName'] : 'USER';
        $base_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $base_code_name), 0, 8));
        do {
            $new_code = $base_code . strtoupper(wp_generate_password(4, false, false));
        } while ($wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '_canna_referral_code' AND meta_value = %s", $new_code)) !== null);
        update_user_meta($user_id, '_canna_referral_code', $new_code);

        if (!empty($params['referralCode'])) {
            $referring_users = get_users([
                'meta_key'   => '_canna_referral_code',
                'meta_value' => sanitize_text_field($params['referralCode']),
                'number'     => 1,
                'fields'     => 'ID'
            ]);

            if (!empty($referring_users)) {
                $options = get_option('canna_rewards_options', []);
                $signup_points = !empty($options['referral_signup_points']) ? (int)$options['referral_signup_points'] : 50;
                
                update_user_meta($user_id, '_canna_referred_by_user_id', $referring_users[0]);
                
                Canna_Points_Handler::add_user_points($user_id, $signup_points, 'Welcome bonus for using a referral link.');
            }
        }

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
        if (!is_email($email) || !email_exists($email)) return $success_response;
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

        $transient_key = 'magic_jwt_' . substr(hash('sha256', $token), 0, 40);
        $cached_jwt = get_transient($transient_key);
        if ($cached_jwt) {
            return new WP_REST_Response(['success' => true, 'token' => $cached_jwt], 200);
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
        
        if (!defined('JWT_AUTH_SECRET_KEY')) {
            return new WP_Error('jwt_not_configured', 'JWT Authentication is not properly configured on the server.', ['status' => 500]);
        }

        if (!class_exists('Firebase\\JWT\\JWT')) {
            return new WP_Error('jwt_library_missing', 'The required JWT library is not available.', ['status' => 500]);
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

        set_transient($transient_key, $jwt, 5 * MINUTE_IN_SECONDS);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return new WP_REST_Response(['success' => true, 'token' => $jwt], 200);
    }
}