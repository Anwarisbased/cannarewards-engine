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
        $profile_data = $this->user_service->get_current_user_full_profile_data();

        if ( empty( $profile_data ) ) {
            return ApiResponse::not_found('User profile not found.');
        }

        return ApiResponse::success(['profile' => $profile_data]);
    }

    public function update_profile( UpdateProfileRequest $request ) {
        try {
            $user_id = get_current_user_id();
            $command = $request->to_command($user_id); // Pass the user ID to the command
            $this->user_service->handle($command);
            
            // After updating, get the fresh profile data to return
            $updated_profile = $this->user_service->get_current_user_full_profile_data();
            return ApiResponse::success(['profile' => $updated_profile]);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'update_failed', 500);
        }
    }
}