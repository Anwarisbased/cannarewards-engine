<?php
namespace CannaRewards\Includes;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles third-party integrations, CORS headers, and compatibility fixes.
 */
class Integrations {

    public static function init() {
        // Handle CORS pre-flight requests.
        add_action('init', [self::class, 'handle_cors_preflight']);

        // Modify REST API headers for CORS. Priority 15 to run after default hooks.
        add_action('rest_api_init', [self::class, 'modify_rest_cors_headers'], 15);

        // Add a fallback for WooCommerce REST API authentication.
        add_filter('woocommerce_rest_check_authentication', [self::class, 'wc_rest_auth_fallback'], 20, 2);
        
        // Loosen WooCommerce product read permissions for logged-in users.
        add_filter('woocommerce_rest_check_permissions', [self::class, 'wc_rest_product_permissions'], 99, 4);
    }
    
    private static function get_allowed_origins(): array {
        return ['http://localhost:3000', 'https://cannarewards-pwa.vercel.app'];
    }

    public static function handle_cors_preflight() {
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            $allowed_origins = self::get_allowed_origins();
            if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins, true)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
                header('Access-Control-Allow-Credentials: true');
                status_header(200);
                exit();
            }
        }
    }

    public static function modify_rest_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) {
            $allowed_origins = self::get_allowed_origins();
            if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins, true)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            return $value;
        });
    }

    public static function wc_rest_auth_fallback($user, $result) {
        if (is_wp_error($result) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            list($type, $auth) = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            if (strtolower($type) === 'basic' && class_exists('WC_REST_Authentication')) {
                // @codingStandardsIgnoreStart
                list($username, $password) = explode(':', base64_decode($auth));
                // @codingStandardsIgnoreEnd
                $api_keys_class = new \WC_REST_Authentication();
                $user_id = $api_keys_class->perform_basic_authentication($username, $password);
                if ($user_id) {
                    return $user_id;
                }
            }
        }
        return $user;
    }

    public static function wc_rest_product_permissions($permission, $context, $object_id, $post_type) {
        if ($context === 'read' && $post_type === 'product' && is_user_logged_in()) {
            return true;
        }
        return $permission;
    }
}