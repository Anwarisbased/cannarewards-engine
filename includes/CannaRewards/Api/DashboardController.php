<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Dashboard Controller (V2)
 * Gathers and serves all dynamically calculated data for the main user dashboard.
 */
class DashboardController {
    private $user_service;

    public function __construct(UserService $user_service) {
        $this->user_service = $user_service;
    }

    /**
     * Callback for GET /v2/users/me/dashboard.
     */
    public function get_dashboard_data( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return ApiResponse::forbidden('User not authenticated.');
        }

        try {
            // The UserService now orchestrates all the data gathering.
            $dashboard_data = $this->user_service->get_user_dashboard_data($user_id);
            return ApiResponse::success($dashboard_data);
        } catch ( Exception $e ) {
            // Log the actual error for better debugging in production.
            error_log('Dashboard data retrieval failed: ' . $e->getMessage());
            return ApiResponse::error('Could not retrieve dashboard data.', 'dashboard_error', 500);
        }
    }
}