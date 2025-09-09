<?php
namespace CannaRewards\Api;

use WP_REST_Request;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\RegisterWithTokenCommand;
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

    public function register_user( WP_REST_Request $request ) {
        try {
            $params = $request->get_json_params();
            $command = new CreateUserCommand(
                sanitize_email($params['email'] ?? ''),
                $params['password'] ?? '',
                sanitize_text_field($params['firstName'] ?? ''),
                sanitize_text_field($params['lastName'] ?? ''),
                sanitize_text_field($params['phone'] ?? ''),
                (bool) ($params['agreedToTerms'] ?? false),
                (bool) ($params['agreedToMarketing'] ?? false),
                sanitize_text_field($params['referralCode'] ?? null)
            );

            $result = $this->user_service->handle($command);
            return ApiResponse::success($result, 201);
        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'registration_failed', $e->getCode() ?: 400);
        }
    }
    
    public function register_with_token( WP_REST_Request $request ) {
        try {
            $params = $request->get_json_params();
            $command = new RegisterWithTokenCommand(
                sanitize_email($params['email'] ?? ''),
                $params['password'] ?? '',
                sanitize_text_field($params['firstName'] ?? ''),
                sanitize_text_field($params['lastName'] ?? ''),
                sanitize_text_field($params['phone'] ?? ''),
                (bool) ($params['agreedToTerms'] ?? false),
                (bool) ($params['agreedToMarketing'] ?? false),
                sanitize_text_field($params['referralCode'] ?? null),
                sanitize_text_field($params['registration_token'] ?? '')
            );

            $result = $this->user_service->handle($command);
            
            // Directly return the payload in a standard response, just like the login_user method does.
            // This bypasses the 'data' wrapper and ensures a consistent auth response.
            return new \WP_REST_Response($result, 200);

        } catch ( Exception $e ) {
            return ApiResponse::error($e->getMessage(), 'token_registration_failed', $e->getCode() ?: 400);
        }
    }

    public function login_user( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        $password = $params['password'] ?? '';

        if ( empty( $email ) || empty( $password ) ) {
            return ApiResponse::bad_request('Email and password are required.');
        }

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
        wp_mail($email, 'Your Password Reset Request', "Click to reset: $reset_link \n\nThis link expires in 1 hour.");
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