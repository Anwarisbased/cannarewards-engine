<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use CannaRewards\Api\Requests\GenerateCodesRequest; // Import the new request
use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ActionLogRepository;

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
    public static function generate_codes(GenerateCodesRequest $request) {
        /** @var RewardCodeRepository $repo */
        $repo = \CannaRewards()->get(RewardCodeRepository::class);
        $generated_codes = $repo->generateCodes($request->get_sku(), $request->get_quantity());

        return new WP_REST_Response([
            'success' => true,
            'message' => "{$request->get_quantity()} codes generated for SKU: {$request->get_sku()}",
            'codes' => $generated_codes
        ], 200);
    }

    /**
     * A debug endpoint to view the new action log.
     */
    public static function debug_view_log(WP_REST_Request $request) {
        /** @var ActionLogRepository $repo */
        $repo = \CannaRewards()->get(ActionLogRepository::class);
        $results = $repo->getRecentLogs(100);
        return new WP_REST_Response($results, 200);
    }
}