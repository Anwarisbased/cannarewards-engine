<?php
namespace CannaRewards\Api;

use CannaRewards\Api\Policies\CanViewOwnResourcePolicy;
use Psr\Container\ContainerInterface;
use WP_REST_Request;

class Router {
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function registerRoutes(): void {
        add_action('rest_api_init', [$this, 'defineRoutes']);
    }

    public function defineRoutes(): void {
        $v2_namespace = 'rewards/v2';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();
        
        // --- REFACTORED ROUTING ---
        // We now define the FormRequest class for each route where applicable.
        $routes = [
            '/users/me/session' => ['GET', SessionController::class, 'get_session_data', $permission_auth],
            '/auth/register' => ['POST', AuthController::class, 'register_user', $permission_public, Requests\RegisterUserRequest::class],
            '/auth/register-with-token' => ['POST', AuthController::class, 'register_with_token', $permission_public, Requests\RegisterWithTokenRequest::class],
            '/auth/login' => ['POST', AuthController::class, 'login_user', $permission_public, Requests\LoginFormRequest::class],
            '/actions/claim' => ['POST', ClaimController::class, 'process_claim', $permission_auth, Requests\ClaimRequest::class],
            '/actions/redeem' => ['POST', RedeemController::class, 'process_redemption', $permission_auth, Requests\RedeemRequest::class],
            '/unauthenticated/claim' => ['POST', ClaimController::class, 'process_unauthenticated_claim', $permission_public, Requests\UnauthenticatedClaimRequest::class],
            '/users/me/profile' => ['POST', ProfileController::class, 'update_profile', $permission_auth, Requests\UpdateProfileRequest::class],
            '/users/me/referrals/nudge' => ['POST', ReferralController::class, 'get_nudge_options', $permission_auth, Requests\NudgeReferralRequest::class],
            
            // <<<--- REFACTOR: Move the legacy route into the main array
            '/users/me/orders' => ['GET', OrdersController::class, 'get_orders', $permission_auth],
            // <<<--- REFACTOR: Add password routes with Form Requests
            '/auth/request-password-reset' => ['POST', AuthController::class, 'request_password_reset', $permission_public, Requests\RequestPasswordResetRequest::class],
            '/auth/perform-password-reset' => ['POST', AuthController::class, 'perform_password_reset', $permission_public, Requests\PerformPasswordResetRequest::class],
        ];

        foreach ($routes as $endpoint => $config) {
            list($method, $controllerClass, $callbackMethod, $permission, $formRequestClass) = array_pad($config, 5, null);

            register_rest_route($v2_namespace, $endpoint, [
                'methods' => $method,
                // Use our new factory to create the callback
                'callback' => $this->create_route_callback($controllerClass, $callbackMethod, $formRequestClass),
                'permission_callback' => $permission
            ]);
        }
    }

    /**
     * A factory that wraps controller callbacks to enable Form Request injection.
     */
    private function create_route_callback(string $controllerClass, string $methodName, ?string $formRequestClass = null) {
        return function (WP_REST_Request $request) use ($controllerClass, $methodName, $formRequestClass) {
            try {
                $controller = $this->container->get($controllerClass);
                $args = [];

                if ($formRequestClass) {
                    // If a FormRequest is defined, create it. This handles all validation.
                    $formRequest = new $formRequestClass($request);
                    $args[] = $formRequest;
                } else {
                    // Otherwise, just pass the original WP_REST_Request
                    $args[] = $request;
                }

                // Call the controller method with the prepared arguments.
                return call_user_func_array([$controller, $methodName], $args);

            } catch (Exceptions\ValidationException $e) {
                // Return a 422 Unprocessable Entity response for validation errors.
                $error = new \WP_Error('validation_failed', $e->getMessage(), ['status' => 422, 'errors' => $e->getErrors()]);
                return rest_ensure_response($error);
            } catch (\Exception $e) {
                // Generic error handling for everything else.
                $statusCode = $e->getCode() && is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
                $error = new \WP_Error('internal_error', $e->getMessage(), ['status' => $statusCode]);
                return rest_ensure_response($error);
            }
        };
    }
}