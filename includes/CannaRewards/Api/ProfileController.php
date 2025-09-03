<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;
use CannaRewards\Services\UserService;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Profile Service Controller (V2)
 */
class ProfileController {
    private $user_service;

    public function __construct() {
        $this->user_service = new UserService();
    }

    /**
     * Callback for GET /users/me/profile.
     */
    public function get_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $profile_data = $this->user_service->get_full_profile_data( $user_id );

        if ( empty( $profile_data ) ) {
            return new WP_Error( 'not_found', 'User profile not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $profile_data, 200 );
    }

    /**
     * Callback for POST /users/me/profile.
     */
    public function update_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $data    = $request->get_json_params();

        if ( empty( $data ) ) {
            return new WP_Error( 'bad_request', 'No data provided for update.', [ 'status' => 400 ] );
        }

        try {
            $updated_profile = $this->user_service->update_user_profile( $user_id, $data );
            return new WP_REST_Response( $updated_profile, 200 );
        } catch ( Exception $e ) {
            return new WP_Error( 'update_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }
}