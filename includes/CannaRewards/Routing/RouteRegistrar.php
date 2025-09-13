<?php
namespace CannaRewards\Routing;

use CannaRewards\Api\Policies\ApiPolicyInterface;

/**
 * API routing configuration.
 */
class RouteRegistrar {
    const API_VERSION = 'v2';
    const API_NAMESPACE = 'rewards/v2';

    /**
     * Get all API routes.
     *
     * @return array
     */
    public static function getRoutes(): array {
        return [
            // Session routes
            '/users/me/session' => [
                'methods' => 'GET',
                'controller' => \CannaRewards\Api\SessionController::class,
                'method' => 'get_session_data',
                'permission' => 'auth',
            ],

            // Authentication routes
            '/auth/register' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'register_user',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RegisterUserRequest::class,
            ],
            
            '/auth/register-with-token' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'register_with_token',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RegisterWithTokenRequest::class,
            ],
            
            '/auth/login' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'login_user',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\LoginFormRequest::class,
            ],
            
            '/auth/request-password-reset' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'request_password_reset',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RequestPasswordResetRequest::class,
            ],
            
            '/auth/perform-password-reset' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'perform_password_reset',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\PerformPasswordResetRequest::class,
            ],

            // Action routes
            '/actions/claim' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ClaimController::class,
                'method' => 'process_claim',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\ClaimRequest::class,
            ],
            
            '/actions/redeem' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\RedeemController::class,
                'method' => 'process_redemption',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\RedeemRequest::class,
            ],

            // Unauthenticated routes
            '/unauthenticated/claim' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ClaimController::class,
                'method' => 'process_unauthenticated_claim',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\UnauthenticatedClaimRequest::class,
            ],

            // User profile routes
            '/users/me/profile' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ProfileController::class,
                'method' => 'update_profile',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\UpdateProfileRequest::class,
            ],

            // Referral routes
            '/users/me/referrals/nudge' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ReferralController::class,
                'method' => 'get_nudge_options',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\NudgeReferralRequest::class,
            ],

            // Legacy routes
            '/users/me/orders' => [
                'methods' => 'GET',
                'controller' => \CannaRewards\Api\OrdersController::class,
                'method' => 'get_orders',
                'permission' => 'auth',
            ],
        ];
    }

    /**
     * Get permission callback for a route.
     *
     * @param string|ApiPolicyInterface $permission
     * @return callable
     */
    public static function getPermissionCallback($permission): callable {
        if (is_string($permission) && class_exists($permission) && is_subclass_of($permission, ApiPolicyInterface::class)) {
            // If it's a class name that implements ApiPolicyInterface, return a closure that resolves and runs it
            return function (\WP_REST_Request $request) use ($permission) {
                /** @var ApiPolicyInterface $policy */
                $policy = CannaRewards()->get($permission);
                return $policy->can($request);
            };
        }
        
        // Fallback to the old key-based system
        switch ($permission) {
            case 'auth':
                return function() { return is_user_logged_in(); };
            case 'public':
            default:
                return '__return_true';
        }
    }
}