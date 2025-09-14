<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use CannaRewards\Api\Responders\SuccessResponder;
use CannaRewards\Api\Responders\ErrorResponder;
use CannaRewards\Api\Responders\BadRequestResponder;
use CannaRewards\Api\Responders\NotFoundResponder;
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
     * @return SuccessResponder|ErrorResponder The API response.
     */
    public function get_products( WP_REST_Request $request ) {
        try {
            $products = $this->catalogService->get_all_reward_products();
            return new SuccessResponder($products);
        } catch (Exception $e) {
            return new ErrorResponder('Failed to fetch products.', 'server_error', 500);
        }
    }

    /**
     * Callback for GET /v2/catalog/products/{id}
     * Fetches a single reward product and adds eligibility context for the current user.
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return SuccessResponder|BadRequestResponder|NotFoundResponder The API response.
     */
    public function get_product( WP_REST_Request $request ) {
        $product_id = (int) $request->get_param('id');
        if ( empty($product_id) ) {
            return new BadRequestResponder('Product ID is required.');
        }

        $user_id = get_current_user_id();
        $product_data = $this->catalogService->get_product_with_eligibility($product_id, $user_id);

        if (!$product_data) {
            return new NotFoundResponder('Product not found.');
        }
        
        return new SuccessResponder($product_data);
    }
}