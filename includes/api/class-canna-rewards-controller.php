<?php
/**
 * Handles Rewards & Core Logic API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Rewards_Controller {
    
    /**
     * Registers all rewards-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_loggedin = function () { return is_user_logged_in(); };

        register_rest_route($base, '/claim',         ['methods' => 'POST', 'callback' => [__CLASS__, 'claim_reward_code'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/redeem',        ['methods' => 'POST', 'callback' => [__CLASS__, 'redeem_reward'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/point-history', ['methods' => 'GET',  'callback' => [__CLASS__, 'get_point_history'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/my-orders',     ['methods' => 'GET',  'callback' => [__CLASS__, 'get_my_orders'], 'permission_callback' => $permission_loggedin]);
    }

    // =========================================================================
    // CALLBACK METHODS
    // =========================================================================

    public static function claim_reward_code(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $code_to_claim = sanitize_text_field($request->get_param('code'));

        if (empty($code_to_claim)) {
            return new WP_Error('rest_bad_request', 'A code must be provided.', ['status' => 400]);
        }
        
        $table_name = $wpdb->prefix . 'canna_reward_codes';
        $code_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE code = %s", $code_to_claim));
        
        if (!$code_data) {
            return new WP_Error('rest_code_invalid', 'This code is invalid.', ['status' => 404]);
        }
        if ($code_data->is_used == 1) {
            return new WP_Error('rest_code_already_used', 'This code has already been used.', ['status' => 400]);
        }
        
        $has_completed_first_scan = get_user_meta($user_id, '_has_completed_first_scan', true);
        $is_first_scan = ($has_completed_first_scan === false || $has_completed_first_scan === '');
        
        $base_points = (int) $code_data->points;
        $points_awarded = $base_points;
        
        // --- Tier-Based Point Multiplier Logic ---
        $current_rank = get_user_current_rank($user_id);
        if (isset($current_rank['key'])) {
            $rank_post = get_page_by_path($current_rank['key'], OBJECT, 'canna_rank');
            if ($rank_post) {
                $multiplier = (float) get_post_meta($rank_post->ID, 'point_multiplier', true);
                if ($multiplier > 1) {
                    $points_awarded = floor($base_points * $multiplier);
                }
            }
        }
        
        $description = 'Claimed points from product SKU: ' . $code_data->sku;
        $response_data = ['success' => true, 'newBalance' => 0, 'firstScanBonus' => ['isEligible' => false]];

        if ($is_first_scan) {
            $settings = get_option('canna_rewards_options', []);
            $welcome_product_id = isset($settings['welcome_reward_product']) ? (int)$settings['welcome_reward_product'] : 0;
            if ($welcome_product_id > 0 && ($product = wc_get_product($welcome_product_id))) {
                // If there's a welcome gift, award enough points to claim it
                $points_cost = (int) get_post_meta($welcome_product_id, 'points_cost', true);
                $points_awarded = $points_cost > 0 ? $points_cost : $points_awarded;
                $response_data['message'] = 'Welcome! ' . $points_awarded . ' Points Added!';
                $response_data['firstScanBonus'] = ['isEligible' => true, 'rewardName' => $product->get_name(), 'rewardProductId' => $welcome_product_id];
            } else {
                $response_data['message'] = 'Welcome! ' . $points_awarded . ' Points Added!';
            }
            // Referral bonus logic
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
        
        // Trigger achievement check on scan
        if (class_exists('Canna_Achievement_Handler')) {
            Canna_Achievement_Handler::check_on_scan($user_id);
        }

        return new WP_REST_Response($response_data, 200);
    }

    public static function redeem_reward(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $product_id = $request->get_param('productId');
        $shipping_details = $request->get_param('shippingDetails');
        if (empty($product_id)) {
            return new WP_Error('rest_bad_request', 'Product ID is required.', ['status' => 400]);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('rest_product_invalid', 'Invalid product.', ['status' => 404]);
        }
        
        $points_cost = (int) get_post_meta($product_id, 'points_cost', true);
        if ($points_cost <= 0) {
            return new WP_Error('rest_not_redeemable', 'This product cannot be redeemed with points.', ['status' => 400]);
        }
        
        if (get_user_points_balance($user_id) < $points_cost) {
            return new WP_Error('rest_insufficient_points', 'Not enough points to redeem this reward.', ['status' => 403]);
        }

        // --- Check stock levels ---
        if (!$product->is_in_stock()) {
            return new WP_Error('rest_out_of_stock', 'Sorry, this reward is currently out of stock.', ['status' => 400]);
        }

        // --- Check for rank requirement ---
        $required_rank_slug = get_post_meta($product_id, '_required_rank', true);
        if (!empty($required_rank_slug)) {
            $user_rank_data = get_user_current_rank($user_id);
            $all_ranks = canna_get_rank_structure();
            
            $user_rank_points = isset($all_ranks[$user_rank_data['key']]) ? $all_ranks[$user_rank_data['key']]['points'] : 0;
            $required_rank_points = isset($all_ranks[$required_rank_slug]) ? $all_ranks[$required_rank_slug]['points'] : PHP_INT_MAX;

            if ($user_rank_points < $required_rank_points) {
                return new WP_Error('rest_rank_too_low', 'You have not unlocked the rank required for this reward.', ['status' => 403]);
            }
        }
        
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

            // Set order total to 0 by applying the points as a "fee"
            $fee = new WC_Order_Item_Fee();
            $fee->set_name("Points Redemption");
            $fee->set_amount(-$product->get_price());
            $fee->set_total(-$product->get_price());
            $order->add_item($fee);

            $order->calculate_totals();
            $order->update_status('processing', 'Reward redeemed with points.');
            
            // Deduct points
            $new_balance = Canna_Points_Handler::add_user_points($user_id, -$points_cost, 'Redeemed: ' . $product->get_name());
            
            // Reduce stock
            wc_update_product_stock($product, 1, 'decrease');

            // Trigger achievement check on redeem
            if (class_exists('Canna_Achievement_Handler')) {
                Canna_Achievement_Handler::check_on_redeem($user_id);
            }

            return new WP_REST_Response(['success' => true, 'message' => 'Reward redeemed successfully!', 'newBalance' => $new_balance, 'orderId' => $order->get_id()], 200);
        } catch (Exception $e) { 
            return new WP_Error('rest_order_creation_failed', 'Could not create order: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    public static function get_point_history(WP_REST_Request $request) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare("SELECT points, description, log_date FROM {$wpdb->prefix}canna_points_log WHERE user_id = %d ORDER BY log_date DESC LIMIT 50", get_current_user_id()));
        return new WP_REST_Response($results, 200);
    }

    public static function get_my_orders(WP_REST_Request $request) {
        $orders = wc_get_orders(['customer_id' => get_current_user_id(), 'status' => ['wc-processing', 'wc-completed', 'wc-cancelled', 'wc-failed', 'wc-refunded'], 'limit' => -1, 'orderby' => 'date', 'order' => 'DESC']);
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
}