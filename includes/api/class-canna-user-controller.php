<?php
/**
 * Handles User & Session API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_User_Controller {

    /**
     * Registers all user and session-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_loggedin = function () { return is_user_logged_in(); };
        $permission_public   = '__return_true';

        register_rest_route($base, '/me',              ['methods' => 'GET',  'callback' => [__CLASS__, 'get_user_data'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/register',         ['methods' => 'POST', 'callback' => [__CLASS__, 'register_user'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/login',            ['methods' => 'POST', 'callback' => [__CLASS__, 'proxy_login'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/me/update',        ['methods' => 'POST', 'callback' => [__CLASS__, 'update_user_profile'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/me/referrals',     ['methods' => 'GET',  'callback' => [__CLASS__, 'get_my_referrals'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/me/referrals/nudge', ['methods' => 'POST', 'callback' => [__CLASS__, 'send_nudge'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/password/request', ['methods' => 'POST', 'callback' => [__CLASS__, 'request_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/password/reset',   ['methods' => 'POST', 'callback' => [__CLASS__, 'perform_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/request-magic-link', ['methods' => 'POST', 'callback' => [__CLASS__, 'request_magic_link'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/validate-magic-link', ['methods' => 'POST', 'callback' => [__CLASS__, 'validate_magic_link'], 'permission_callback' => $permission_public]);
    }

    // =========================================================================
    // CALLBACK METHODS
    // =========================================================================

    public static function get_user_data(WP_REST_Request $request) {
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $balance = get_user_points_balance($user_id);
        $lifetime_points = get_user_lifetime_points($user_id);
        $current_rank = get_user_current_rank($user_id);
        $all_ranks = canna_get_rank_structure();
        $settings = get_option('canna_rewards_options', []);
        
        $theme_options = [
            'primaryColor'   => $settings['primary_color'] ?? '#000000',
            'secondaryColor' => $settings['secondary_color'] ?? '#FBBF24',
            'primaryFont'    => $settings['primary_font'] ?? 'Inter',
        ];
        $response_settings = [
            'referralBannerText' => $settings['referral_banner_text'] ?? '🎁 Earn More By Inviting Your Friends',
            'theme'              => $theme_options,
        ];
        
        // --- FIX: REMOVED server-side filtering. Now we send ALL rewards and let the frontend decide how to display them. ---
        $all_rewards = [];
        
        $all_reward_products_query = new WP_Query([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'points_cost', 'compare' => 'EXISTS'],
                ['key' => 'points_cost', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']
            ]
        ]);

        if ($all_reward_products_query->have_posts()) {
            while ($all_reward_products_query->have_posts()) {
                $all_reward_products_query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }
                
                $required_rank_slug = get_post_meta($product_id, '_required_rank', true);
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');

                $all_rewards[] = [
                    'id' => $product_id, 
                    'name' => get_the_title(), 
                    'points_cost' => (int) get_post_meta($product_id, 'points_cost', true), 
                    'image' => $image_url ?: wc_placeholder_img_src(),
                    'tierRequired' => $required_rank_slug ?: null, // Send the rank requirement
                    'isInStock' => $product->is_in_stock(), // Send stock status
                ];
            }
        }
        wp_reset_postdata();

        $shipping_meta = [
            'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'shipping_last_name'  => get_user_meta($user_id, 'shipping_last_name', true),
            'shipping_address_1'  => get_user_meta($user_id, 'shipping_address_1', true),
            'shipping_city'       => get_user_meta($user_id, 'shipping_city', true),
            'shipping_state'      => get_user_meta($user_id, 'shipping_state', true),
            'shipping_postcode'   => get_user_meta($user_id, 'shipping_postcode', true),
        ];

        return new WP_REST_Response([
            'id' => $user_id, 'email' => $user->user_email, 'firstName' => $user->first_name, 'lastName' => $user->last_name,
            'points' => $balance, 'rank' => $current_rank, 'lifetimePoints' => $lifetime_points, 'allRanks' => $all_ranks,
            'eligibleRewards' => $all_rewards, // The property name is now a bit of a misnomer, but we keep it for frontend compatibility
            'settings' => $response_settings, 'referralCode' => get_user_meta($user_id, '_canna_referral_code', true),
            'shipping' => $shipping_meta,
            'date_of_birth' => get_user_meta($user_id, 'date_of_birth', true)
        ], 200);
    }

    public static function get_my_referrals(WP_REST_Request $request) {
        $current_user_id = get_current_user_id();
        $args = [
            'meta_key'   => '_canna_referred_by_user_id',
            'meta_value' => $current_user_id,
            'fields'     => ['ID', 'display_name', 'user_registered', 'user_email'],
        ];
        $referred_users = get_users($args);
        $response_data = [];
        foreach ($referred_users as $user) {
            $has_scanned = get_user_meta($user->ID, '_has_completed_first_scan', true);
            $response_data[] = [
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'join_date'    => date('F j, Y', strtotime($user->user_registered)),
                'status'       => $has_scanned ? 'Bonus Awarded' : 'Pending First Scan',
                'status_key'   => $has_scanned ? 'awarded' : 'pending',
            ];
        }
        return new WP_REST_Response($response_data, 200);
    }

    public static function send_nudge(WP_REST_Request $request) {
        $referrer = wp_get_current_user();
        $params = $request->get_json_params();
        $referee_email = sanitize_email($params['email'] ?? '');
        if (empty($referee_email)) return new WP_Error('bad_request', 'Referee email is required.', ['status' => 400]);
        $referee = get_user_by('email', $referee_email);
        if (!$referee || (int) get_user_meta($referee->ID, '_canna_referred_by_user_id', true) !== $referrer->ID) {
            return new WP_Error('forbidden', 'You are not authorized to nudge this user.', ['status' => 403]);
        }
        $transient_key = 'canna_nudge_limit_' . $referrer->ID . '_' . $referee->ID;
        if (get_transient($transient_key)) {
            return new WP_Error('too_many_requests', 'You can only send one reminder every 24 hours.', ['status' => 429]);
        }
        $options = get_option('canna_rewards_options', []);
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : 'https://cannarewards-pwa.vercel.app';
        $scan_link = "{$base_url}/scan";
        $referee_name = $referee->first_name ? ' ' . $referee->first_name : '';
        $share_text = "Hey" . $referee_name . "! Just a friendly reminder from " . $referrer->first_name . " to scan your first CannaRewards product. You're one scan away from unlocking your welcome gift! Scan here: " . $scan_link;
        set_transient($transient_key, true, DAY_IN_SECONDS);
        return new WP_REST_Response(['success' => true, 'message' => 'Nudge ready to be shared.', 'share_text' => $share_text], 200);
    }

    public static function register_user(WP_REST_Request $request) {
        if (!get_option('users_can_register')) return new WP_Error('registration_disabled', 'User registration is currently disabled.', ['status' => 403]);
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $username = sanitize_user($params['username'] ?? $email, true);
        $password = $params['password'] ?? '';
        $age_gate = isset($params['agreedToTerms']) ? (bool) $params['agreedToTerms'] : false;
        if (!$age_gate) {
            return new WP_Error('age_gate_required', 'You must certify that you are 21 years of age or older.', ['status' => 400]);
        }
        if (empty($username) || empty($password) || empty($email)) return new WP_Error('registration_failed_empty', 'Username, password, and email are required.', ['status' => 400]);
        if (strlen($password) < 6) return new WP_Error('registration_failed_password', 'Password must be at least 6 characters long.', ['status' => 400]);
        if (!is_email($email)) return new WP_Error('registration_failed_email', 'Invalid email address.', ['status' => 400]);
        if (username_exists($username)) return new WP_Error('registration_failed_username_exists', 'An account with that username already exists.', ['status' => 409]);
        if (email_exists($email)) return new WP_Error('registration_failed_email_exists', 'An account with that email address already exists.', ['status' => 409]);
        $user_id = wp_insert_user(['user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'first_name' => sanitize_text_field($params['firstName'] ?? ''), 'last_name' => sanitize_text_field($params['lastName'] ?? ''), 'role' => 'subscriber']);
        if (is_wp_error($user_id)) return new WP_Error('registration_failed_wp_error', $user_id->get_error_message(), ['status' => 500]);
        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        update_user_meta($user_id, '_has_completed_first_scan', false);
        global $wpdb;
        $base_code_name = !empty($params['firstName']) ? $params['firstName'] : 'USER';
        $base_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $base_code_name), 0, 8));
        do { $new_code = $base_code . strtoupper(wp_generate_password(4, false, false)); } while ($wpdb->get_var($wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '_canna_referral_code' AND meta_value = %s", $new_code)) !== null);
        update_user_meta($user_id, '_canna_referral_code', $new_code);
        if (!empty($params['phone'])) update_user_meta($user_id, 'phone_number', sanitize_text_field($params['phone']));
        if (isset($params['agreedToMarketing'])) update_user_meta($user_id, 'marketing_consent', (bool) $params['agreedToMarketing']);
        if (!empty($params['referralCode'])) {
            $referring_users = get_users(['meta_key' => '_canna_referral_code', 'meta_value' => sanitize_text_field($params['referralCode']), 'number' => 1, 'fields' => 'ID']);
            if (!empty($referring_users)) {
                $options = get_option('canna_rewards_options', []);
                $signup_points = !empty($options['referral_signup_points']) ? (int)$options['referral_signup_points'] : 50;
                update_user_meta($user_id, '_canna_referred_by_user_id', $referring_users[0]);
                Canna_Points_Handler::add_user_points($user_id, $signup_points, 'Welcome bonus for using a referral link.');
            }
        }
        if (!empty($params['code'])) {
            $original_user = get_current_user_id();
            wp_set_current_user($user_id);
            $claim_request = new WP_REST_Request('POST');
            $claim_request->set_param('code', sanitize_text_field($params['code']));
            Canna_Rewards_Controller::claim_reward_code($claim_request); 
            wp_set_current_user($original_user);
        }
        return new WP_REST_Response(['success' => true, 'message' => 'Registration successful. Please log in.', 'user_id' => $user_id], 201);
    }

    public static function proxy_login(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';
        if (empty($email) || empty($password)) {
            return new WP_Error('missing_credentials', 'Email and password are required.', ['status' => 400]);
        }

        $internal_request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
        $internal_request->set_body_params([
            'username' => $email,
            'password' => $password
        ]);

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

    public static function update_user_profile(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $update_data = ['ID' => $user_id];
        if (isset($params['firstName'])) $update_data['first_name'] = sanitize_text_field($params['firstName']);
        if (isset($params['lastName'])) $update_data['last_name'] = sanitize_text_field($params['lastName']);
        if (count($update_data) > 1) wp_update_user($update_data);
        if (isset($params['phone'])) update_user_meta($user_id, 'phone_number', sanitize_text_field($params['phone']));
        if (isset($params['dateOfBirth'])) update_user_meta($user_id, 'date_of_birth', sanitize_text_field($params['dateOfBirth']));
        return new WP_REST_Response(['success' => true, 'message' => 'Profile updated successfully.'], 200);
    }

    public static function request_password_reset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $success_response = new WP_REST_Response(['success' => true, 'message' => 'If an account with that email exists, a reset link has been sent.'], 200);
        if (!is_email($email) || !email_exists($email)) return $success_response;
        $user = get_user_by('email', $email);
        $token = bin2hex(random_bytes(32));
        update_user_meta($user->ID, '_password_reset_token', $token);
        update_user_meta($user->ID, '_password_reset_expiration', time() + HOUR_IN_SECONDS);
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
        $user = get_user_by('email', $email);
        if (!$user || empty($token) || empty($password)) return new WP_Error('invalid_request', 'Invalid request.', ['status' => 400]);
        $stored_token = get_user_meta($user->ID, '_password_reset_token', true);
        $expiration = get_user_meta($user->ID, '_password_reset_expiration', true);
        if (empty($stored_token) || !hash_equals($stored_token, $token) || time() > $expiration) return new WP_Error('invalid_token', 'Your password reset token is invalid or has expired.', ['status' => 400]);
        reset_password($user, $password);
        delete_user_meta($user->ID, '_password_reset_token');
        delete_user_meta($user->ID, '_password_reset_expiration');
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
        
        $users = get_users([
            'meta_key' => '_magic_login_token',
            'meta_value' => $token,
            'number' => 1,
            'fields' => ['ID'],
        ]);

        if (empty($users)) {
            return new WP_Error('invalid_token', 'Your login link is invalid.', ['status' => 400]);
        }

        $user_id = $users[0]->ID;
        $expiration = get_user_meta($user_id, '_magic_login_expiration', true);

        if (time() > $expiration) {
            delete_user_meta($user_id, '_magic_login_token');
            delete_user_meta($user_id, '_magic_login_expiration');
            return new WP_Error('expired_token', 'Your login link has expired. Please request a new one.', ['status' => 400]);
        }

        delete_user_meta($user_id, '_magic_login_token');
        delete_user_meta($user_id, '_magic_login_expiration');
        
        $jwt_auth_public = new JWT_Auth_Public();
        $jwt_token = $jwt_auth_public->auth_handler->generate_token($user_id);

        if (is_wp_error($jwt_token)) {
            return $jwt_token;
        }

        return new WP_REST_Response(['success' => true, 'token' => $jwt_token], 200);
    }
}