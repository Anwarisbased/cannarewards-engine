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
 * A lean controller for handling reward redemptions.
 */
class RedeemController {
    private $economy_service;

    public function __construct() {
        $this->economy_service = new EconomyService();
    }

    /**
     * Callback for POST /v2/actions/redeem
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return WP_REST_Response|WP_Error The API response.
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
            // Use specific error codes to allow the frontend to react differently.
            $error_code = $e->getCode();
            $status_code = 400; // Default to Bad Request
            if ($error_code === 1) { // Our custom code for insufficient points
                $status_code = 402; // Payment Required
            } elseif ($error_code === 2) { // Our custom code for rank required
                $status_code = 403; // Forbidden
            }

            return new WP_Error( 'redemption_failed', $e->getMessage(), [ 'status' => $status_code ] );
        }
    }
}