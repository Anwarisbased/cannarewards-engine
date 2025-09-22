<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\CatalogService;
use Exception;

/**
 * Catalog Service Controller (V2)
 * Acts as a secure proxy to WooCommerce product data.
 */
class CatalogController {
    private CatalogService $catalogService;

    public function __construct(CatalogService $catalogService) {
        $this->catalogService = $catalogService;
    }

    private function send_cached_response(array $data, int $minutes = 5): \WP_REST_Response {
        $response = ApiResponse::success($data);
        // This is the correct way to add headers. It must be done on the final WP_REST_Response object.
        $response->header('Cache-Control', "public, s-maxage=" . ($minutes * 60) . ", max-age=" . ($minutes * 60));
        return $response;
    }

    /**
     * Callback for GET /v2/catalog/products
     * Fetches a list of all reward products.
     */
    public function get_products(WP_REST_Request $request): \WP_REST_Response {
        try {
            $products = $this->catalogService->get_all_reward_products();
            // Use the new helper method which now returns a WP_REST_Response
            return $this->send_cached_response(['products' => $products]);
        } catch (Exception $e) {
            // ApiResponse::error returns a WP_Error, which the REST server handles correctly.
            return rest_ensure_response(ApiResponse::error('Failed to fetch products.', 'server_error', 500));
        }
    }

    /**
     * Callback for GET /v2/catalog/products/{id}
     */
    public function get_product(WP_REST_Request $request): \WP_REST_Response {
        $product_id = (int) $request->get_param('id');
        if (empty($product_id)) {
            return rest_ensure_response(ApiResponse::bad_request('Product ID is required.'));
        }

        $user_id = get_current_user_id();
        $product_data = $this->catalogService->get_product_with_eligibility($product_id, $user_id);

        if (!$product_data) {
            return rest_ensure_response(ApiResponse::not_found('Product not found.'));
        }
        
        return ApiResponse::success($product_data);
    }
}