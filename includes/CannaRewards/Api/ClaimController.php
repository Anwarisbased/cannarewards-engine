<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\EconomyService;
use CannaRewards\Commands\ProcessProductScanCommand;
use CannaRewards\Commands\ProcessUnauthenticatedClaimCommand;
use CannaRewards\Api\Requests\ClaimRequest;
use CannaRewards\Api\Requests\UnauthenticatedClaimRequest; // Import the new request
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

    public function process_claim( ClaimRequest $request ) {
        $user_id = get_current_user_id();
        
        try {
            $command = $request->to_command($user_id);
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'claim_failed', 409);
        }
    }

    public function process_unauthenticated_claim( UnauthenticatedClaimRequest $request ) {
        try {
            $command = $request->to_command();
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'unauthenticated_claim_failed', 409);
        }
    }
}