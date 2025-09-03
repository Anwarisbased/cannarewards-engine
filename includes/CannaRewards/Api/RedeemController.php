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
 * Redeem Service Controller (V2)
 */
class RedeemController {
    private $economy_service;

    public function __construct() {
        $this->economy_service = new EconomyService();
    }

    /**
     * Callback for POST /v2/actions/redeem
     */
    public function process_redemption( WP_REST_Request $request ) {
        $user_id          = get_current_user_id();
        $product_id       = (int) $request->get_param('productId');
        $shipping_details = (array) $request->get_param('shippingDetails');

        if ( empty( $product_id ) ) {
            return new WP_Error( 'bad_request', 'Product ID is required.', [ 'status' => 400 ] );
        }

        try {
            $result = $this->economy_service->redeem_points( $user_id, $product_id, $shipping_details );
            return new WP_REST_Response( $result, 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'redemption_failed', $e->getMessage(), [ 'status' => 402 ] );
        }
    }
}