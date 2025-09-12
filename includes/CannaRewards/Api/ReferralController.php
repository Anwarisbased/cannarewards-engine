<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\ReferralService;
use CannaRewards\Api\Requests\NudgeReferralRequest; // Import the new request
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Referral Controller (V2)
 * Handles fetching referral data for the authenticated user.
 */
class ReferralController {
    private $referral_service;

    public function __construct(ReferralService $referral_service) {
        $this->referral_service = $referral_service;
    }

    /**
     * Callback for GET /v2/users/me/referrals
     */
    public function get_my_referrals( WP_REST_Request $request ) {
        try {
            $user_id = get_current_user_id();
            $referrals = $this->referral_service->get_user_referrals( $user_id );
            return ApiResponse::success(['referrals' => $referrals]);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 'referral_fetch_failed', 500);
        }
    }

    /**
     * Callback for POST /v2/users/me/referrals/nudge
     */
    public function get_nudge_options( NudgeReferralRequest $request ) {
        $user_id = get_current_user_id();
        $referee_email = $request->get_referee_email();

        try {
            $options = $this->referral_service->get_nudge_options_for_referee( $user_id, $referee_email );
            return ApiResponse::success($options);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 'nudge_failed', 403);
        }
    }
}