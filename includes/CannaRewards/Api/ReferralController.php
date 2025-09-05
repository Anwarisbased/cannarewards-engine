<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use CannaRewards\Services\ReferralService;

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

    public function __construct() {
        $this->referral_service = new ReferralService();
    }

    /**
     * Callback for GET /v2/users/me/referrals
     */
    public function get_my_referrals( WP_REST_Request $request ): WP_REST_Response {
        $user_id = get_current_user_id();
        $referrals = $this->referral_service->get_user_referrals( $user_id );
        return new WP_REST_Response( $referrals, 200 );
    }

    /**
     * Callback for POST /v2/users/me/referrals/nudge
     */
    public function get_nudge_options( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $user_id = get_current_user_id();
        $referee_email = sanitize_email( $request->get_param('email') );

        if ( empty($referee_email) ) {
            return new WP_Error('bad_request', 'Referee email is required.', ['status' => 400]);
        }

        try {
            $options = $this->referral_service->get_nudge_options_for_referee( $user_id, $referee_email );
            return new WP_REST_Response( $options, 200 );
        } catch (\Exception $e) {
            return new WP_Error('nudge_failed', $e->getMessage(), ['status' => 403]);
        }
    }
}