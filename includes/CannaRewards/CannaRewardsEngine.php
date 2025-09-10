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
        
        $this->container->get(Services\GamificationService::class);
        $this->container->get(Services\EconomyService::class);
        $this->container->get(Services\ReferralService::class);
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

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();

        // --- Session Endpoint Fix ---
        register_rest_route($v2_namespace, '/users/me/session', [
            'methods' => 'GET', 
            'callback' => function() { 
                $user_service = $this->container->get(Services\UserService::class); 
                $session_dto = $user_service->get_user_session_data(get_current_user_id());
                // The (array) cast is critical. It recursively converts the main DTO
                // and the nested RankDTO into a plain array for the JSON response.
                return Api\ApiResponse::success((array) $session_dto); 
            }, 
            'permission_callback' => $permission_auth
        ]);
        // --- End Fix ---

        // A more maintainable way to register routes
        $routes = [
            // Auth
            '/auth/register' => ['POST', Api\AuthController::class, 'register_user'],
            '/auth/register-with-token' => ['POST', Api\AuthController::class, 'register_with_token'],
            '/auth/login' => ['POST', Api\AuthController::class, 'login_user'],

            // Actions
            '/actions/claim' => ['POST', Api\ClaimController::class, 'process_claim'],
            '/actions/redeem' => ['POST', Api\RedeemController::class, 'process_redemption'],
            '/unauthenticated/claim' => ['POST', Api\ClaimController::class, 'process_unauthenticated_claim'],

            // User Data
            '/users/me/orders' => ['GET', Api\OrdersController::class, 'get_orders']
            // ... Add all other routes here in the same format
        ];

        foreach ($routes as $endpoint => $config) {
            list($method, $controllerClass, $callbackMethod) = $config;
            // Determine permission based on endpoint
            $permission = (strpos($endpoint, 'unauthenticated') === false && strpos($endpoint, 'auth') === false) 
                ? $permission_auth 
                : $permission_public;

            register_rest_route($v2_namespace, $endpoint, [
                'methods' => $method,
                'callback' => [$this->container->get($controllerClass), $callbackMethod],
                'permission_callback' => $permission
            ]);
        }
    }
}