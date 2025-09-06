<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Repositories\OrderRepository;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class OrdersController {
    private $order_repository;

    public function __construct(OrderRepository $order_repository) {
        $this->order_repository = $order_repository;
    }

    public function get_orders( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $limit   = (int) $request->get_param('limit') ?: 50;

        try {
            $orders_data = $this->order_repository->getUserOrders( $user_id, $limit );
            return ApiResponse::success(['orders' => $orders_data]);
        } catch ( Exception $e ) {
            return ApiResponse::error('Could not retrieve user orders.', 'orders_error', 500);
        }
    }
}