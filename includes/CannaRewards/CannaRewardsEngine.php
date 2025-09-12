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

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();
        
        // A centralized, maintainable way to register all routes.
        $routes = [
            // Session
            '/users/me/session' => ['GET', Api\SessionController::class, 'get_session_data', $permission_auth],

            // Auth
            '/auth/register' => ['POST', Api\AuthController::class, 'register_user', $permission_public],
            '/auth/register-with-token' => ['POST', Api\AuthController::class, 'register_with_token', $permission_public],
            '/auth/login' => ['POST', Api\AuthController::class, 'login_user', $permission_public],

            // Actions
            '/actions/claim' => ['POST', Api\ClaimController::class, 'process_claim', $permission_auth],
            '/actions/redeem' => ['POST', Api\RedeemController::class, 'process_redemption', $permission_auth],
            '/unauthenticated/claim' => ['POST', Api\ClaimController::class, 'process_unauthenticated_claim', $permission_public],

            // User Data
            '/users/me/orders' => ['GET', Api\OrdersController::class, 'get_orders', $permission_auth]
        ];

        foreach ($routes as $endpoint => $config) {
            list($method, $controllerClass, $callbackMethod, $permission) = $config;

            register_rest_route($v2_namespace, $endpoint, [
                'methods' => $method,
                'callback' => [$this->container->get($controllerClass), $callbackMethod],
                'permission_callback' => $permission
            ]);
        }
    }
}