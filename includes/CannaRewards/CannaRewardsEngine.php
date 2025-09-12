<?php
namespace CannaRewards;

use CannaRewards\Admin\AdminMenu;
use CannaRewards\Admin\AchievementMetabox;
use CannaRewards\Admin\CustomFieldMetabox;
use CannaRewards\Admin\ProductMetabox;
use CannaRewards\Admin\TriggerMetabox;
use CannaRewards\Admin\UserProfile;
use CannaRewards\Api;
use CannaRewards\Services;
use CannaRewards\Includes\DB;
use CannaRewards\Includes\Integrations;
use CannaRewards\Api\Exceptions\ValidationException;
use CannaRewards\Api\FormRequest;
use Psr\Container\ContainerInterface;

final class CannaRewardsEngine {
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        add_action('init', [$this, 'init']);
    }
    
    public function init() {
        Integrations::init();

        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>CannaRewards Engine Warning:</strong> WooCommerce is not installed or active.</p></div>';
            });
            return;
        }

        $this->init_wordpress_components();
        
        // Instantiate all event-driven services to register their listeners.
        $this->container->get(Services\GamificationService::class);
        $this->container->get(Services\EconomyService::class);
        $this->container->get(Services\ReferralService::class);
        $this->container->get(Services\RankService::class); // RankService now listens for events
        $this->container->get(Services\FirstScanBonusService::class); // Our new service
        $this->container->get(Services\StandardScanService::class); // Our other new service
    }
    
    private function init_wordpress_components() {
        AdminMenu::init();
        UserProfile::init();
        ProductMetabox::init();
        new AchievementMetabox();
        new CustomFieldMetabox();
        new TriggerMetabox();
        
        canna_register_rank_post_type();
        canna_register_achievement_post_type();
        canna_register_custom_field_post_type();
        canna_register_trigger_post_type();
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        register_activation_hook(CANNA_PLUGIN_FILE, [DB::class, 'activate']);
    }
    
    /**
     * A factory that wraps controller callbacks to enable Form Request injection.
     */
    private function create_route_callback(string $controllerClass, string $methodName, ?string $formRequestClass = null) {
        return function (\WP_REST_Request $request) use ($controllerClass, $methodName, $formRequestClass) {
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

            } catch (ValidationException $e) {
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

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();
        
        // --- REFACTORED ROUTING ---
        // We now define the FormRequest class for each route where applicable.
        $routes = [
            '/users/me/session' => ['GET', Api\SessionController::class, 'get_session_data', $permission_auth],
            '/auth/register' => ['POST', Api\AuthController::class, 'register_user', $permission_public, Api\Requests\RegisterUserRequest::class],
            '/auth/register-with-token' => ['POST', Api\AuthController::class, 'register_with_token', $permission_public, Api\Requests\RegisterWithTokenRequest::class],
            '/auth/login' => ['POST', Api\AuthController::class, 'login_user', $permission_public, Api\Requests\LoginFormRequest::class],
            '/actions/claim' => ['POST', Api\ClaimController::class, 'process_claim', $permission_auth, Api\Requests\ClaimRequest::class],
            '/actions/redeem' => ['POST', Api\RedeemController::class, 'process_redemption', $permission_auth, Api\Requests\RedeemRequest::class],
            '/unauthenticated/claim' => ['POST', Api\ClaimController::class, 'process_unauthenticated_claim', $permission_public, Api\Requests\UnauthenticatedClaimRequest::class],
            '/users/me/profile' => ['POST', Api\ProfileController::class, 'update_profile', $permission_auth, Api\Requests\UpdateProfileRequest::class],
            '/users/me/referrals/nudge' => ['POST', Api\ReferralController::class, 'get_nudge_options', $permission_auth, Api\Requests\NudgeReferralRequest::class],
            // ... (other routes for now will remain the same until we create FormRequests for them)
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

        // --- OLDER ROUTES (to be refactored) ---
        // This is a temporary measure. We will move all routes to the array above as we create FormRequests for them.
        register_rest_route($v2_namespace, '/users/me/orders', [ 'methods' => 'GET', 'callback' => [$this->container->get(Api\OrdersController::class), 'get_orders'], 'permission_callback' => $permission_auth ]);
    }
}