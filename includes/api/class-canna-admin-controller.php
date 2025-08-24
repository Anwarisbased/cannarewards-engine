<?php
/**
 * Handles Admin & Debug API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Admin_Controller {
    
    /**
     * Registers all admin-only REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_admin = function () { return current_user_can('manage_options'); };
        
        register_rest_route($base, '/generate-codes', ['methods' => 'POST', 'callback' => [__CLASS__, 'generate_codes'], 'permission_callback' => $permission_admin]);
        register_rest_route($base, '/debug-log',      ['methods' => 'GET',  'callback' => [__CLASS__, 'debug_view_log'], 'permission_callback' => $permission_admin]);
    }

    // =========================================================================
    // CALLBACK METHODS
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