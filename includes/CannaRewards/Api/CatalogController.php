<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use CannaRewards\Services\CatalogService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Catalog Service Controller (V2)
 * Acts as a secure proxy to WooCommerce product data.
 */
class CatalogController {
    private CatalogService $catalogService;

    public function __construct(CatalogService $catalogService) {
        $this->catalogService = $catalogService;
    }

    /**
     * Callback for GET /v2/catalog/products
     * Fetches a list of all reward products.
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return WP_REST_Response|WP_Error The API response.
     */
    public function get_products( WP_REST_Request $request ) {
        if ( ! function_exists('wc_get_products') ) {
            return new WP_Error( 'woocommerce_inactive', 'WooCommerce is not active.', [ 'status' => 503 ] );
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => -1, // Retrieve all products
        ]);

        $formatted_products = [];
        foreach ( $products as $product ) {
            // Only include products that can be redeemed (i.e., have a points_cost).
            $points_cost = $product->get_meta('points_cost');
            if ( ! empty($points_cost) ) {
                $formatted_products[] = $this->catalogService->format_product_for_api($product);
            }
        }

        return new WP_REST_Response( $formatted_products, 200 );
    }

    /**
     * Callback for GET /v2/catalog/products/{id}
     * Fetches a single reward product and adds eligibility context for the current user.
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return WP_REST_Response|WP_Error The API response.
     */
    public function get_product( WP_REST_Request $request ) {
        $product_id = (int) $request->get_param('id');
        if ( empty($product_id) ) {
            return new WP_Error( 'bad_request', 'Product ID is required.', [ 'status' => 400 ] );
        }

        $user_id = get_current_user_id();
        $product_data = $this->catalogService->get_product_with_eligibility($product_id, $user_id);

        if (!$product_data) {
            return new WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
        }
        
        return new WP_REST_Response( $product_data, 200 );
    }
}