<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\EconomyService;
use CannaRewards\Commands\RedeemRewardCommand;
use CannaRewards\Api\Requests\RedeemRequest; // Import the new request
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class RedeemController {
    private $economy_service;

    public function __construct(EconomyService $economy_service) {
        $this->economy_service = $economy_service;
    }

    public function process_redemption( RedeemRequest $request ) {
        $user_id = get_current_user_id();
        
        try {
            $command = $request->to_command($user_id);
            $result = $this->economy_service->handle($command);
            return ApiResponse::success($result);
        } catch ( Exception $e ) {
            $status_code = 400; // Default
            if ($e->getCode() === 1) $status_code = 402; // Insufficient points
            if ($e->getCode() === 2) $status_code = 403; // Rank required
            return ApiResponse::error($e->getMessage(), 'redemption_failed', $status_code);
        }
    }
}