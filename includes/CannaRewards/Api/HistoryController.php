<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use CannaRewards\Services\ActionLogService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * History Service Controller (V2)
 */
class HistoryController {
    private $action_log_service;

    public function __construct() {
        $this->action_log_service = new ActionLogService();
    }

    /**
     * Callback for GET /v2/users/me/history.
     */
    public function get_history( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $limit   = (int) $request->get_param('limit') ?: 50;

        try {
            $history_data = $this->action_log_service->get_user_points_history( $user_id, $limit );
            return new WP_REST_Response( $history_data, 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'history_error', 'Could not retrieve user history.', [ 'status' => 500 ] );
        }
    }
}