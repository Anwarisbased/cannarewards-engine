<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Api\Requests\RegisterUserRequest;
use CannaRewards\Api\Requests\RegisterWithTokenRequest;
use CannaRewards\Api\Requests\LoginFormRequest; // Import the new request
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
        return new \WP_REST_Response($result, 200);
    }

    public function login_user( LoginFormRequest $request ) {
        $credentials = $request->get_credentials();
        $email = $credentials['email'];
        $password = $credentials['password'];

        $internal_request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $internal_request->set_body_params(['username' => $email, 'password' => $password]);
        $response = rest_do_request($internal_request);

        if ( $response->is_error() ) {
            return ApiResponse::forbidden('Invalid username or password.');
        }
        
        return new \WP_REST_Response( $response->get_data(), 200 );
    }

    public function request_password_reset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $email = sanitize_email($params['email'] ?? '');
        $success_response = new \WP_REST_Response(['success' => true, 'message' => 'If an account with that email exists, a reset link has been sent.'], 200);
        if (!is_email($email) || !email_exists($email)) {
            return $success_response;
        }
        $user = get_user_by('email', $email);
        $token = get_password_reset_key($user);
        if (is_wp_error($token)) {
            return ApiResponse::error('Could not generate reset token.', 'token_generation_failed', 500);
        }
        $options = get_option('canna_rewards_options');
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : home_url();
        $reset_link = "$base_url/reset-password?token=$token&email=" . rawurlencode($email);
        wp_mail($email, 'Your Password Reset Request', "Click to reset: $reset_link 

This link expires in 1 hour.");
        return $success_response;
    }

    public function perform_password_reset(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $token = sanitize_text_field($params['token'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        $password = $params['password'] ?? '';
        
        $user = check_password_reset_key($token, $email);
        if (is_wp_error($user)) {
             return ApiResponse::error('Your password reset token is invalid or has expired.', 'invalid_token', 400);
        }

        reset_password($user, $password);
        
        return new \WP_REST_Response(['success' => true, 'message' => 'Password has been reset successfully. You can now log in.'], 200);
    }
}