<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Api\Requests\RegisterUserRequest;
use CannaRewards\Api\Requests\RegisterWithTokenRequest;
use CannaRewards\Api\Requests\LoginFormRequest;
use CannaRewards\Api\Requests\RequestPasswordResetRequest; // Import the new request
use CannaRewards\Api\Requests\PerformPasswordResetRequest; // Import the new request
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AuthController {
    private $user_service;

    public function __construct(UserService $user_service) {
        $this->user_service = $user_service;
    }

    public function register_user( RegisterUserRequest $request ) {
        // The controller is now incredibly simple.
        // All validation, sanitization, and data transformation happened before this method was even called.
        $command = $request->to_command();
        $result = $this->user_service->handle($command);
        return ApiResponse::success($result, 201);
    }
    
    public function register_with_token( RegisterWithTokenRequest $request ) {
        // This entire method is now clean. The try/catch is handled by the route factory.
        // Validation and data transformation is handled by the Form Request.
        $command = $request->to_command();
        $result = $this->user_service->handle($command);
        // The result is already a properly formatted JWT response, so we don't need to wrap it
        return new \WP_REST_Response($result, 200);
    }

    public function login_user( LoginFormRequest $request ) {
        $credentials = $request->get_credentials();
        $email = $credentials['email'];
        $password = $credentials['password'];

        try {
            $login_data = $this->user_service->login($email, $password);
            return ApiResponse::success($login_data);
        } catch (Exception $e) {
            // The UserService::login method throws an exception on failure.
            return ApiResponse::forbidden('Invalid username or password.');
        }
    }

    public function request_password_reset(RequestPasswordResetRequest $request) {
        $this->user_service->request_password_reset($request->getEmail());
        return ApiResponse::success(['message' => 'If an account with that email exists, a reset link has been sent.']);
    }

    public function perform_password_reset(PerformPasswordResetRequest $request) {
        $data = $request->getResetData();
        $this->user_service->perform_password_reset($data['token'], $data['email'], $data['password']);
        return ApiResponse::success(['message' => 'Password has been reset successfully. You can now log in.']);
    }
}