<?php
/**
 * Handles User Profile API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_User_Controller {

    /**
     * Registers all user profile-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_loggedin = function () { return is_user_logged_in(); };

        register_rest_route($base, '/me',        ['methods' => 'GET',  'callback' => [__CLASS__, 'get_user_data'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/me/update', ['methods' => 'POST', 'callback' => [__CLASS__, 'update_user_profile'], 'permission_callback' => $permission_loggedin]);
    }

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
                    'tierRequired' => $required_rank_slug ?: null,
                    'isInStock' => $product->is_in_stock(),
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
            'eligibleRewards' => $all_rewards,
            'settings' => $response_settings, 'referralCode' => get_user_meta($user_id, '_canna_referral_code', true),
            'shipping' => $shipping_meta,
            'date_of_birth' => get_user_meta($user_id, 'date_of_birth', true)
        ], 200);
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
}