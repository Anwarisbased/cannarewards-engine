<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Catalog Service Controller (V2)
 * Acts as a secure proxy to WooCommerce product data.
 */
class CatalogController {

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
                $formatted_products[] = $this->format_product_for_api($product);
            }
        }

        return new WP_REST_Response( $formatted_products, 200 );
    }

    /**
     * Callback for GET /v2/catalog/products/{id}
     * Fetches a single reward product.
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return WP_REST_Response|WP_Error The API response.
     */
    public function get_product( WP_REST_Request $request ) {
        $product_id = (int) $request->get_param('id');
        if ( empty($product_id) ) {
            return new WP_Error( 'bad_request', 'Product ID is required.', [ 'status' => 400 ] );
        }

        $product = wc_get_product($product_id);
        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->format_product_for_api($product), 200 );
    }

    /**
     * A helper function to consistently format product data for the API response.
     * This ensures the frontend receives data in the exact structure it expects.
     *
     * @param \WC_Product $product The WooCommerce product object.
     * @return array The formatted product data.
     */
    private function format_product_for_api( $product ): array {
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();

        return [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'description' => $product->get_description(),
            'images'      => [
                ['src' => $image_url]
            ],
            'meta_data'   => [
                [
                    'key'   => 'points_cost',
                    'value' => $product->get_meta('points_cost'),
                ],
                [
                    'key'   => '_required_rank',
                    'value' => $product->get_meta('_required_rank'),
                ],
            ],
        ];
    }
}