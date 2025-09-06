<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\ActionLogService;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * History Service Controller (V2)
 */
class HistoryController {
    private $action_log_service;

    public function __construct(ActionLogService $action_log_service) {
        $this->action_log_service = $action_log_service;
    }

    /**
     * Callback for GET /v2/users/me/history.
     */
    public function get_history( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $limit   = (int) $request->get_param('limit') ?: 50;

        try {
            $history_data = $this->action_log_service->get_user_points_history( $user_id, $limit );
            return ApiResponse::success(['history' => $history_data]);
        } catch ( Exception $e ) {
            return ApiResponse::error('Could not retrieve user history.', 'history_error', 500);
        }
    }
}