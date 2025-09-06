<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\EconomyService;
use CannaRewards\Commands\RedeemRewardCommand;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class RedeemController {
    private $economy_service;

    public function __construct(EconomyService $economy_service) {
        $this->economy_service = $economy_service;
    }

    public function process_redemption( WP_REST_Request $request ) {
        $user_id          = get_current_user_id();
        $product_id       = (int) $request->get_param('productId');
        $shipping_details = (array) $request->get_param('shippingDetails');

        if ( empty( $product_id ) ) {
            return ApiResponse::bad_request('Product ID is required.');
        }

        try {
            $command = new RedeemRewardCommand($user_id, $product_id, $shipping_details);
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            $status_code = 400; // Default
            if ($e->getCode() === 1) $status_code = 402; // Insufficient points
            if ($e->getCode() === 2) $status_code = 403; // Rank required
            return ApiResponse::error($e->getMessage(), 'redemption_failed', $status_code);
        }
    }
}