<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use CannaRewards\Services\ContentService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Page Service Controller (V2)
 */
class PageController {
    private $content_service;

    // <<<--- REFACTOR: Inject the dependency
    public function __construct(ContentService $content_service) {
        $this->content_service = $content_service;
    }

    /**
     * Callback for GET /v2/pages/{slug}.
     */
    public function get_page( WP_REST_Request $request ) {
        $slug = $request->get_param( 'slug' );
        if ( empty( $slug ) ) {
            return ApiResponse::bad_request('Page slug is required.');
        }

        try {
            $page_data = $this->content_service->get_page_by_slug( $slug );
            if ( is_null( $page_data ) ) {
                return ApiResponse::not_found('The requested page could not be found.');
            }
            return ApiResponse::success($page_data);
        } catch ( Exception $e ) {
            return ApiResponse::error('Could not retrieve page content.', 'page_error', 500);
        }
    }
}