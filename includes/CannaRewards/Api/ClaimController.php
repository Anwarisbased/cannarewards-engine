<?php
namespace CannaRewards\Api;

use CannaRewards\Api\Requests\ClaimRequest;
use CannaRewards\Api\Requests\UnauthenticatedClaimRequest;
use CannaRewards\Services\EconomyService;

class ClaimController
{
    private EconomyService $economy_service;

    public function __construct(EconomyService $economy_service)
    {
        $this->economy_service = $economy_service;
    }

    public function process_claim(ClaimRequest $request)
    {
        $user_id = get_current_user_id();
        $command = $request->to_command($user_id);
        $this->economy_service->handle($command);
        
        // On success, return 202 Accepted. The Router's generic catch block will handle any exceptions.
        return new \WP_REST_Response(['success' => true, 'status' => 'accepted'], 202);
    }

    public function process_unauthenticated_claim(UnauthenticatedClaimRequest $request)
    {
        $command = $request->to_command();
        $result = $this->economy_service->handle($command);

        // On success, return the result. The Router's generic catch block will handle any exceptions.
        return ApiResponse::success($result);
    }
}