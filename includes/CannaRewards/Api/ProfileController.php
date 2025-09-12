<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\UpdateProfileCommand;
use CannaRewards\Api\Requests\UpdateProfileRequest; // Import the new request
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

    public function update_profile( UpdateProfileRequest $request ) {
        $user_id = get_current_user_id();
        
        try {
            $command = $request->to_command($user_id);
            $this->user_service->handle($command);
            
            // After updating, get the fresh profile data to return
            $updated_profile = $this->user_service->get_full_profile_data( $user_id );
            return ApiResponse::success(['profile' => $updated_profile]);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'update_failed', 500);
        }
    }
}