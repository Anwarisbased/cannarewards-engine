<?php
/**
 * Handles Content & Marketing API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Content_Controller {
    
    /**
     * Registers all content-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_public = '__return_true';

        register_rest_route($base, '/referral-gift', ['methods' => 'GET', 'callback' => [__CLASS__, 'get_referral_gift_info'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/preview-reward', ['methods' => 'GET', 'callback' => [__CLASS__, 'get_welcome_reward_preview'], 'permission_callback' => $permission_public]);
        register_rest_route($base, '/page/(?P<slug>[a-zA-Z0-9-]+)', ['methods' => 'GET', 'callback' => [__CLASS__, 'get_page_content'], 'permission_callback' => $permission_public]);
    }

    // =========================================================================
    // CALLBACK METHODS
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
}