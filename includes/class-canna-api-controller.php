<?php
/**
 * Canna Rewards API Controller
 *
 * This class is responsible for registering all custom REST API endpoints
 * for the CannaRewards PWA and providing the callback methods to handle
 * the logic for each endpoint.
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_API_Controller {

    /**
     * Initializes the class by adding the main action for route registration.
     */
    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Registers all custom REST API routes for the plugin.
     */
    public static function register_routes() {
        $permission_loggedin = function () { return is_user_logged_in(); };
        $permission_public   = '__return_true';
        $permission_admin    = function () { return current_user_can('manage_options'); };

        $base = 'rewards/v1';

        // User & Session Endpoints
        register_rest_route($base, '/me',              ['methods' => 'GET',  'callback' => [self::class, 'get_user_data'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/register',         ['methods' => 'POST', 'callback' => [self::class, 'register_user'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/login',            ['methods' => 'POST', 'callback' => [self::class, 'proxy_login'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/me/update',        ['methods' => 'POST', 'callback' => [self::class, 'update_user_profile'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/me/referrals',     ['methods' => 'GET',  'callback' => [self::class, 'get_my_referrals'], 'permission_callback' => $permission_loggedin]);
        // --- SIMPLIFIED NUDGE ROUTE ---
        register_rest_route($base, '/me/referrals/nudge', ['methods' => 'POST', 'callback' => [self::class, 'send_nudge'], 'permission_callback' => $permission_loggedin]);
        // --- END SIMPLIFICATION ---
        register_rest_route($base, '/password/request', ['methods' => 'POST', 'callback' => [self::class, 'request_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/password/reset',   ['methods' => 'POST', 'callback' => [self::class, 'perform_password_reset'], 'permission_callback' => $permission_public]);
        
        // Rewards & Logic Endpoints
        register_rest_route($base, '/claim',         ['methods' => 'POST', 'callback' => [self::class, 'claim_reward_code'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/redeem',        ['methods' => 'POST', 'callback' => [self::class, 'redeem_reward'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/point-history', ['methods' => 'GET',  'callback' => [self::class, 'get_point_history'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/my-orders',     ['methods' => 'GET',  'callback' => [self::class, 'get_my_orders'], 'permission_callback' => $permission_loggedin]);

        // Content & Marketing Endpoints
        register_rest_route($base, '/referral-gift', ['methods' => 'GET', 'callback' => [self::class, 'get_referral_gift_info'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/preview-reward', ['methods' => 'GET', 'callback' => [self::class, 'get_welcome_reward_preview'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/page/(?P<slug>[a-zA-Z0-9-]+)', ['methods' => 'GET', 'callback' => [self::class, 'get_page_content'], 'permission_callback' => $permission_public]);
        
        // Admin & Debug Endpoints
        register_rest_route($base, '/generate-codes', ['methods' => 'POST', 'callback' => [self::class, 'generate_codes'], 'permission_callback' => $permission_admin]);
        register_rest_route($base, '/debug-log',      ['methods' => 'GET',  'callback' => [self::class, 'debug_view_log'], 'permission_callback' => $permission_admin]);
    }

    // =========================================================================
    // USER & SESSION CALLBACKS
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
        
        $eligible_rewards = [];
        $args = ['post_type' => 'product', 'posts_per_page' => 3, 'meta_query' => [['key' => 'points_cost', 'compare' => 'EXISTS'], ['key' => 'points_cost', 'value' => $balance, 'compare' => '<=', 'type' => 'NUMERIC']]];
        $reward_products = new WP_Query($args);
        if ($reward_products->have_posts()) {
            while ($reward_products->have_posts()) {
                $reward_products->the_post();
                $product = wc_get_product(get_the_ID());
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                $eligible_rewards[] = ['id' => get_the_ID(), 'name' => get_the_title(), 'points_cost' => (int) get_post_meta(get_the_ID(), 'points_cost', true), 'image' => $image_url ?: 'https://via.placeholder.com/150'];
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
            'eligibleRewards' => $eligible_rewards, 'settings' => $response_settings, 'referralCode' => get_user_meta($user_id, '_canna_referral_code', true),
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

        if (empty($referee_email)) {
            return new WP_Error('bad_request', 'Referee email is required.', ['status' => 400]);
        }
        $referee = get_user_by('email', $referee_email);

        if (!$referee || (int) get_user_meta($referee->ID, '_canna_referred_by_user_id', true) !== $referrer->ID) {
            return new WP_Error('forbidden', 'You are not authorized to nudge this user.', ['status' => 403]);
        }
        
        // --- START: Re-implement Rate Limiting ---
        $transient_key = 'canna_nudge_limit_' . $referrer->ID . '_' . $referee->ID;
        if (get_transient($transient_key)) {
            return new WP_Error('too_many_requests', 'You can only send one reminder every 24 hours.', ['status' => 429]);
        }
        // --- END: Re-implement Rate Limiting ---

        // START: Updated code
        $options = get_option('canna_rewards_options', []);
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : 'https://cannarewards-pwa.vercel.app';
        $scan_link = "{$base_url}/scan";
        // END: Updated code
        $referee_name = $referee->first_name ? ' ' . $referee->first_name : '';

        $share_text = "Hey" . $referee_name . "! Just a friendly reminder from " . $referrer->first_name . " to scan your first CannaRewards product. You're one scan away from unlocking your welcome gift! Scan here: " . $scan_link;
        
        // Set the transient to expire in 24 hours to prevent spam.
        set_transient($transient_key, true, DAY_IN_SECONDS);

        return new WP_REST_Response([
            'success' => true, 
            'message' => 'Nudge ready to be shared.',
            'share_text' => $share_text
        ], 200);
    }

    public static function register_user(WP_REST_Request $request) {
        if (!get_option('users_can_register')) return new WP_Error('registration_disabled', 'User registration is currently disabled.', ['status' => 403]);
        
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $username = sanitize_user($params['username'] ?? $email, true);
        $password = $params['password'] ?? '';
        
        // --- START: UPDATED VALIDATION BLOCK ---
        if (empty($username) || empty($password) || empty($email)) {
            return new WP_Error('registration_failed_empty', 'Username, password, and email are required.', ['status' => 400]);
        }
        if (strlen($password) < 6) {
            return new WP_Error('registration_failed_password', 'Password must be at least 6 characters long.', ['status' => 400]);
        }
        if (!is_email($email)) {
            return new WP_Error('registration_failed_email', 'Invalid email address.', ['status' => 400]);
        }
        // --- END: UPDATED VALIDATION BLOCK ---

        if (username_exists($username)) return new WP_Error('registration_failed_username_exists', 'An account with that username already exists.', ['status' => 409]);
        if (email_exists($email)) return new WP_Error('registration_failed_email_exists', 'An account with that email address already exists.', ['status' => 409]);
        
        $user_id = wp_insert_user(['user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'first_name' => sanitize_text_field($params['firstName'] ?? ''), 'last_name' => sanitize_text_field($params['lastName'] ?? ''), 'role' => 'subscriber']);
        if (is_wp_error($user_id)) return new WP_Error('registration_failed_wp_error', $user_id->get_error_message(), ['status' => 500]);
        
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
            self::claim_reward_code($claim_request);
            wp_set_current_user($original_user);
        }
        
        return new WP_REST_Response(['success' => true, 'message' => 'Registration successful. Please log in.', 'user_id' => $user_id], 201);
    }

    public static function proxy_login(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';
        if (empty($email) || empty($password)) return new WP_Error('missing_credentials', 'Email and password are required.', ['status' => 400]);
        $internal_request = new WP_REST_Request('POST', '/jwt-auth/v1/token');
        $internal_request->set_body_params(['username' => $email, 'password' => $password]);
        $response = rest_do_request($internal_request);
        $data = $response->get_data();
        if (isset($data['code']) && strpos($data['code'], 'jwt_auth_') !== false) return new WP_Error('auth_failed', 'Invalid username or password.', ['status' => 403]);
        return $response->is_error() ? $response : new WP_REST_Response($data, 200);
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
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url(); // Use saved URL or fallback to WP URL.
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

    // =========================================================================
    // REWARDS & LOGIC CALLBACKS
    // =========================================================================

    public static function claim_reward_code(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $code_to_claim = sanitize_text_field($request->get_param('code'));
        if (empty($code_to_claim)) return new WP_REST_Response(['success' => false, 'message' => 'A code must be provided.'], 400);
        $table_name = $wpdb->prefix . 'canna_reward_codes';
        $code_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE code = %s", $code_to_claim));
        if (!$code_data) return new WP_REST_Response(['success' => false, 'message' => 'This code is invalid.'], 404);
        if ($code_data->is_used == 1) return new WP_REST_Response(['success' => false, 'message' => 'This code has already been used.'], 400);
        
        $has_completed_first_scan = get_user_meta($user_id, '_has_completed_first_scan', true);
        $is_first_scan = ($has_completed_first_scan === false || $has_completed_first_scan === '');
        $points_awarded = (int) $code_data->points;
        $description = 'Claimed points from product SKU: ' . $code_data->sku;
        $response_data = ['success' => true, 'newBalance' => 0, 'firstScanBonus' => ['isEligible' => false]];

        if ($is_first_scan) {
            $settings = get_option('canna_rewards_options', []);
            $welcome_product_id = isset($settings['welcome_reward_product']) ? (int)$settings['welcome_reward_product'] : 0;
            if ($welcome_product_id > 0 && ($product = wc_get_product($welcome_product_id))) {
                $points_cost = (int) get_post_meta($welcome_product_id, 'points_cost', true);
                $points_awarded = $points_cost > 0 ? $points_cost : $points_awarded;
                $response_data['message'] = 'Welcome! ' . $points_awarded . ' Points Added!';
                $response_data['firstScanBonus'] = ['isEligible' => true, 'rewardName' => $product->get_name(), 'rewardProductId' => $welcome_product_id];
            } else {
                $response_data['message'] = 'Welcome! ' . $points_awarded . ' Points Added!';
            }
            $referrer_id = get_user_meta($user_id, '_canna_referred_by_user_id', true);
            if (!empty($referrer_id)) {
                $options = get_option('canna_rewards_options', []);
                $referrer_bonus = isset($options['referrer_bonus_points']) ? (int)$options['referrer_bonus_points'] : 200;
                $new_user = get_userdata($user_id);
                $new_user_name = $new_user->first_name ?: $new_user->user_email;
                Canna_Points_Handler::add_user_points($referrer_id, $referrer_bonus, 'Referral bonus from your friend: ' . $new_user_name);
            }
            update_user_meta($user_id, '_has_completed_first_scan', true);
        } else {
            $response_data['message'] = $points_awarded . ' Points Added!';
        }
        
        $response_data['newBalance'] = Canna_Points_Handler::add_user_points($user_id, $points_awarded, $description);
        $wpdb->update($table_name, ['is_used' => 1, 'user_id' => $user_id, 'claimed_at' => current_time('mysql', 1)], ['id' => $code_data->id]);
        
        return new WP_REST_Response($response_data, 200);
    }

    public static function redeem_reward(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $product_id = $request->get_param('productId');
        $shipping_details = $request->get_param('shippingDetails');
        if (empty($product_id)) return new WP_REST_Response(['success' => false, 'message' => 'Product ID is required.'], 400);
        $product = wc_get_product($product_id);
        if (!$product) return new WP_REST_Response(['success' => false, 'message' => 'Invalid product.'], 404);
        $points_cost = (int) get_post_meta($product_id, 'points_cost', true);
        if ($points_cost <= 0) return new WP_REST_Response(['success' => false, 'message' => 'This product cannot be redeemed with points.'], 400);
        if (get_user_points_balance($user_id) < $points_cost) return new WP_REST_Response(['success' => false, 'message' => 'Not enough points to redeem this reward.'], 403);
        
        try {
            if (!empty($shipping_details) && is_array($shipping_details)) {
                $address_map = ['firstName' => 'shipping_first_name', 'lastName' => 'shipping_last_name', 'address1' => 'shipping_address_1', 'city' => 'shipping_city', 'state' => 'shipping_state', 'zip' => 'shipping_postcode'];
                foreach ($address_map as $key => $wc_key) {
                    if (isset($shipping_details[$key])) update_user_meta($user_id, $wc_key, sanitize_text_field($shipping_details[$key]));
                }
            }
            $order = wc_create_order(['customer_id' => $user_id]);
            $order->add_product($product, 1);
            $address = ['first_name' => get_user_meta($user_id, 'shipping_first_name', true), 'last_name' => get_user_meta($user_id, 'shipping_last_name', true), 'address_1' => get_user_meta($user_id, 'shipping_address_1', true), 'city' => get_user_meta($user_id, 'shipping_city', true), 'state' => get_user_meta($user_id, 'shipping_state', true), 'postcode' => get_user_meta($user_id, 'shipping_postcode', true), 'country' => 'US', 'email' => wp_get_current_user()->user_email];
            $order->set_address($address, 'shipping');
            $order->set_address($address, 'billing');
            $fee = new WC_Order_Item_Fee();
            $fee->set_name("Points Redemption");
            $fee->set_amount(-$product->get_price());
            $fee->set_total(-$product->get_price());
            $order->add_item($fee);
            $order->calculate_totals();
            $order->update_status('processing', 'Reward redeemed with points.');
            $new_balance = Canna_Points_Handler::add_user_points($user_id, -$points_cost, 'Redeemed: ' . $product->get_name());
            return new WP_REST_Response(['success' => true, 'message' => 'Reward redeemed successfully!', 'newBalance' => $new_balance, 'orderId' => $order->get_id()], 200);
        } catch (Exception $e) { return new WP_REST_Response(['success' => false, 'message' => 'Could not create order: ' . $e->getMessage()], 500); }
    }

    public static function get_point_history(WP_REST_Request $request) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT points, description, log_date FROM {$wpdb->prefix}canna_points_log WHERE user_id = %d ORDER BY log_date DESC LIMIT 50", get_current_user_id()));
        return new WP_REST_Response($results, 200);
    }

    public static function get_my_orders(WP_REST_Request $request) {
        $orders = wc_get_orders(['customer_id' => get_current_user_id(), 'status' => ['wc-processing', 'wc-completed', 'wc-cancelled', 'wc-failed', 'wc-refunded'], 'limit' => -1]);
        $formatted_orders = [];
        foreach ($orders as $order) {
            $items_array = [];
            $image_url = wc_placeholder_img_src();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $items_array[] = $item->get_name();
                if ($product && $product->get_image_id()) $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
            }
            $formatted_orders[] = ['orderId' => $order->get_id(), 'date' => $order->get_date_created()->date('F j, Y'), 'status' => wc_get_order_status_name($order->get_status()), 'items' => implode(', ', $items_array), 'total' => $order->get_total(), 'imageUrl' => $image_url];
        }
        return new WP_REST_Response($formatted_orders, 200);
    }

    // =========================================================================
    // CONTENT & MARKETING CALLBACKS
    // =========================================================================

    public static function get_referral_gift_info(WP_REST_Request $request) {
        $settings = get_option('canna_rewards_options');
        $gift_product_id = isset($settings['referral_signup_gift']) ? (int)$settings['referral_signup_gift'] : 0;
        if ($gift_product_id > 0 && ($product = wc_get_product($gift_product_id))) {
            return new WP_REST_Response(['productId' => $product->get_id(), 'name' => $product->get_name(), 'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium'), 'isReferralGift' => true], 200);
        }
        return new WP_Error('no_referral_gift', 'No referral gift has been configured.', ['status' => 404]);
    }

    public static function get_welcome_reward_preview(WP_REST_Request $request) {
        $settings = get_option('canna_rewards_options');
        $welcome_product_id = isset($settings['welcome_reward_product']) ? (int)$settings['welcome_reward_product'] : 0;
        if ($welcome_product_id > 0 && ($product = wc_get_product($welcome_product_id))) {
            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'medium');
            return new WP_REST_Response(['productId' => $product->get_id(), 'name' => $product->get_name(), 'image' => $image_url ?: wc_placeholder_img_src()], 200);
        }
        return new WP_Error('no_welcome_reward', 'No welcome reward has been configured.', ['status' => 404]);
    }

    public static function get_page_content(WP_REST_Request $request) {
        $page = get_page_by_path($request['slug'], OBJECT, 'page');
        if (!$page) return new WP_Error('not_found', 'Page not found.', ['status' => 404]);
        return new WP_REST_Response(['title' => $page->post_title, 'content' => apply_filters('the_content', $page->post_content)], 200);
    }
    
    // =========================================================================
    // ADMIN & DEBUG CALLBACKS
    // =========================================================================

    public static function generate_codes(WP_REST_Request $request) {
        global $wpdb;
        $params = $request->get_json_params();
        $sku = sanitize_text_field($params['sku'] ?? 'DEFAULT-SKU');
        $points = (int)($params['points'] ?? 100);
        $quantity = (int)($params['quantity'] ?? 10);
        $generated_codes = [];
        for ($i = 0; $i < $quantity; $i++) {
            $new_code = strtoupper($sku) . '-' . wp_generate_password(12, false);
            $wpdb->insert($wpdb->prefix . 'canna_reward_codes', ['code' => $new_code, 'points' => $points, 'sku' => $sku]);
            $generated_codes[] = $new_code;
        }
        return new WP_REST_Response(['success' => true, 'message' => "$quantity codes generated for SKU: $sku", 'codes' => $generated_codes], 200);
    }

    public static function debug_view_log(WP_REST_Request $request) {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}canna_points_log ORDER BY log_id DESC");
        if ($wpdb->last_error) return new WP_REST_Response(['error' => 'Database Error', 'message' => $wpdb->last_error], 500);
        return new WP_REST_Response($results, 200);
    }
}