<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\EconomyService;
use CannaRewards\Commands\ProcessProductScanCommand;
use CannaRewards\Commands\ProcessUnauthenticatedClaimCommand;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class ClaimController {
    private $economy_service;

    public function __construct(EconomyService $economy_service) {
        $this->economy_service = $economy_service;
    }

    public function process_claim( WP_REST_Request $request ) {
        $user_id       = get_current_user_id();
        $code_to_claim = sanitize_text_field( $request->get_param( 'code' ) );

        if ( empty( $code_to_claim ) ) {
            return ApiResponse::bad_request('A code must be provided.');
        }

        try {
            $command = new ProcessProductScanCommand($user_id, $code_to_claim);
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'claim_failed', 409);
        }
    }

    public function process_unauthenticated_claim( WP_REST_Request $request ) {
        $code_to_claim = sanitize_text_field( $request->get_param( 'code' ) );

        if ( empty( $code_to_claim ) ) {
            return ApiResponse::bad_request('A code must be provided.');
        }

        try {
            $command = new ProcessUnauthenticatedClaimCommand($code_to_claim);
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'unauthenticated_claim_failed', 409);
        }
    }
}