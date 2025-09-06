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
        // This is the most important part. We add the headers directly.
        add_action('init', [self::class, 'handle_preflight_and_cors_headers']);
        
        // Remove default WordPress handlers to avoid conflicts.
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) {
            self::add_cors_headers();
            return $value;
        });
    }

    /**
     * Central function to add all required CORS headers.
     */
    private static function add_cors_headers() {
        $origin = get_http_origin();
        $allowed_origins = ['http://localhost:3000', 'https://cannarewards-pwa.vercel.app'];

        if ($origin && in_array($origin, $allowed_origins, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
        }
    }

    /**
     * Handles the pre-flight OPTIONS request aggressively.
     */
    public static function handle_preflight_and_cors_headers() {
        // Always add headers on every request.
        self::add_cors_headers();

        // If it's a pre-flight request, kill the script after sending headers.
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(204); // 204 No Content is appropriate for pre-flight
            exit();
        }
    }
}