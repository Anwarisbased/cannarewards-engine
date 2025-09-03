<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use CannaRewards\Services\EconomyService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Orders Service Controller (V2)
 */
class OrdersController {
    private $economy_service;

    public function __construct() {
        $this->economy_service = new EconomyService();
    }

    /**
     * Callback for GET /v2/users/me/orders.
     */
    public function get_orders( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $limit   = (int) $request->get_param('limit') ?: 50;

        try {
            $orders_data = $this->economy_service->get_user_orders( $user_id, $limit );
            return new WP_REST_Response( $orders_data, 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'orders_error', 'Could not retrieve user orders.', [ 'status' => 500 ] );
        }
    }
}