<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\UpdateProfileCommand;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class ProfileController {
    private $user_service;

    public function __construct(UserService $user_service) {
        $this->user_service = $user_service;
    }

    public function get_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $profile_data = $this->user_service->get_full_profile_data( $user_id );

        if ( empty( $profile_data ) ) {
            return ApiResponse::not_found('User profile not found.');
        }

        return ApiResponse::success(['profile' => $profile_data]);
    }

    public function update_profile( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $data    = $request->get_json_params();

        if ( empty( $data ) ) {
            return ApiResponse::bad_request('No data provided for update.');
        }

        try {
            $command = new UpdateProfileCommand($user_id, $data);
            $this->user_service->handle($command);
            
            // After updating, get the fresh profile data to return
            $updated_profile = $this->user_service->get_full_profile_data( $user_id );
            return ApiResponse::success(['profile' => $updated_profile]);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'update_failed', 500);
        }
    }
}