<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles Admin & Debug API Endpoints
 */
class AdminController {
    
    /**
     * Registers all admin-only REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1'; // These are internal, so keeping v1 is fine for now.
        $permission_admin = function () {
            return current_user_can('manage_options');
        };
        
        register_rest_route($base, '/generate-codes', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'generate_codes'],
            'permission_callback' => $permission_admin
        ]);
        register_rest_route($base, '/debug-log', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'debug_view_log'],
            'permission_callback' => $permission_admin
        ]);
    }

    /**
     * Generates a batch of reward codes.
     */
    public static function generate_codes(WP_REST_Request $request) {
        global $wpdb;
        $params = $request->get_json_params();
        $sku = sanitize_text_field($params['sku'] ?? 'DEFAULT-SKU');
        $quantity = (int)($params['quantity'] ?? 10);
        $generated_codes = [];

        // Note: The 'points' column is deprecated in the new schema.
        // This function would need to be updated if used.
        for ($i = 0; $i < $quantity; $i++) {
            $new_code = strtoupper($sku) . '-' . wp_generate_password(12, false);
            $wpdb->insert(
                $wpdb->prefix . 'canna_reward_codes',
                ['code' => $new_code, 'sku' => $sku]
            );
            $generated_codes[] = $new_code;
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => "$quantity codes generated for SKU: $sku",
            'codes' => $generated_codes
        ], 200);
    }

    /**
     * A debug endpoint to view the new action log.
     */
    public static function debug_view_log(WP_REST_Request $request) {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}canna_user_action_log ORDER BY log_id DESC LIMIT 100");

        if ($wpdb->last_error) {
            return new WP_REST_Response([
                'error' => 'Database Error',
                'message' => $wpdb->last_error
            ], 500);
        }
        return new WP_REST_Response($results, 200);
    }
}