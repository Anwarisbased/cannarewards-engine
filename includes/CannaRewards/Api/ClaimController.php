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
 * Claim Service Controller (V2)
 * A lean controller that delegates all logic to the EconomyService.
 */
class ClaimController {
    private $economy_service;

    public function __construct() {
        $this->economy_service = new EconomyService();
    }

    /**
     * The main callback for the POST /v2/actions/claim endpoint.
     *
     * @param WP_REST_Request $request The incoming API request.
     * @return WP_REST_Response|WP_Error The API response.
     */
    public function process_claim( WP_REST_Request $request ) {
        $user_id       = get_current_user_id();
        $code_to_claim = sanitize_text_field( $request->get_param( 'code' ) );

        if ( empty( $code_to_claim ) ) {
            return new WP_Error( 'bad_request', 'A code must be provided.', [ 'status' => 400 ] );
        }

        try {
            // DELEGATE EVERYTHING to the service. The controller does no thinking.
            $result = $this->economy_service->process_scan( $user_id, $code_to_claim );
            
            // The service now returns a rich response payload that includes any triggered events.
            return new WP_REST_Response( $result, 200 );

        } catch ( Exception $e ) {
            // Catch any errors from the service and format them for the API.
            // A 409 Conflict is more appropriate for an invalid/used code.
            return new WP_Error( 'claim_failed', $e->getMessage(), [ 'status' => 409 ] );
        }
    }
}