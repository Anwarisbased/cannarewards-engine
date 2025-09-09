<?php
namespace CannaRewards;

use CannaRewards\Admin\AdminMenu;
use CannaRewards\Admin\AchievementMetabox;
use CannaRewards\Admin\CustomFieldMetabox;
use CannaRewards\Admin\ProductMetabox;
use CannaRewards\Admin\TriggerMetabox;
use CannaRewards\Admin\UserProfile;
use CannaRewards\Api\AuthController;
use CannaRewards\Api\CatalogController;
use CannaRewards\Api\ClaimController;
use CannaRewards\Api\HistoryController;
use CannaRewards\Api\OrdersController;
use CannaRewards\Api\PageController;
use CannaRewards\Api\ProfileController;
use CannaRewards\Api\RedeemController;
use CannaRewards\Api\ReferralController;
use CannaRewards\Api\UnauthenticatedDataController;
use CannaRewards\Container\DIContainer;
use CannaRewards\Includes\DB;
use CannaRewards\Includes\Integrations;
use CannaRewards\Services;

final class CannaRewardsEngine {
    private static $instance;
    private $container;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->container = new DIContainer();
        add_action('plugins_loaded', [$this, 'init']);
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
    }
    
    private function init_wordpress_components() {
        AdminMenu::init();
        UserProfile::init();
        ProductMetabox::init();
        new AchievementMetabox();
        new CustomFieldMetabox();
        new TriggerMetabox();
        
        add_action('init', 'canna_register_rank_post_type', 0);
        add_action('init', 'canna_register_achievement_post_type', 0);
        add_action('init', 'canna_register_custom_field_post_type', 0);
        add_action('init', 'canna_register_trigger_post_type', 0);
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        register_activation_hook(CANNA_PLUGIN_FILE, ['CannaRewards\Includes\DB', 'activate']);
    }

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $v1_namespace = 'rewards/v1';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();

        $auth_controller     = $this->container->get(Api\AuthController::class);
        $catalog_controller  = $this->container->get(Api\CatalogController::class);
        $claim_controller    = $this->container->get(Api\ClaimController::class);
        $history_controller  = $this->container->get(Api\HistoryController::class);
        $orders_controller   = $this->container->get(Api\OrdersController::class);
        $page_controller     = $this->container->get(Api\PageController::class);
        $profile_controller  = $this->container->get(Api\ProfileController::class);
        $redeem_controller   = $this->container->get(Api\RedeemController::class);
        $referral_controller = $this->container->get(Api\ReferralController::class);
        $unauth_controller   = $this->container->get(Api\UnauthenticatedDataController::class);
        $config_service      = $this->container->get(Services\ConfigService::class);
        $user_service        = $this->container->get(Services\UserService::class);

        register_rest_route($v2_namespace, '/unauthenticated/welcome-reward-preview', ['methods' => 'GET', 'callback' => [$unauth_controller, 'get_welcome_reward_preview'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/unauthenticated/claim', ['methods' => 'POST', 'callback' => [$claim_controller, 'process_unauthenticated_claim'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/register-with-token', ['methods' => 'POST', 'callback' => [$auth_controller, 'register_with_token'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/register', ['methods' => 'POST', 'callback' => [$auth_controller, 'register_user'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/login', ['methods' => 'POST', 'callback' => [$auth_controller, 'login_user'], 'permission_callback' => $permission_public]);
        register_rest_route($v1_namespace, '/password/request', ['methods' => 'POST', 'callback' => [$auth_controller, 'request_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($v1_namespace, '/password/reset', ['methods' => 'POST', 'callback' => [$auth_controller, 'perform_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/app/config', ['methods' => 'GET', 'callback' => [$config_service, 'get_app_config'], 'permission_callback' => $permission_auth]);
        
        register_rest_route($v2_namespace, '/users/me/session', ['methods' => 'GET', 'callback' => function() use ($user_service) { 
            $session_dto = $user_service->get_user_session_data(get_current_user_id());
            // Cast the DTO to an array for the response, which will be JSON encoded.
            return \CannaRewards\Api\ApiResponse::success((array) $session_dto);
        }, 'permission_callback' => $permission_auth]);
        
        register_rest_route($v2_namespace, '/actions/claim', ['methods' => 'POST', 'callback' => [$claim_controller, 'process_claim'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/actions/redeem', ['methods' => 'POST', 'callback' => [$redeem_controller, 'process_redemption'], 'permission_callback' => $permission_auth]);
        
        register_rest_route($v2_namespace, '/users/me/profile', [
            ['methods' => 'GET', 'callback' => [$profile_controller, 'get_profile'], 'permission_callback' => $permission_auth], 
            ['methods' => 'POST', 'callback' => [$profile_controller, 'update_profile'], 'permission_callback' => $permission_auth]
        ]);
        
        register_rest_route($v2_namespace, '/users/me/history', ['methods' => 'GET', 'callback' => [$history_controller, 'get_history'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/orders', ['methods' => 'GET', 'callback' => [$orders_controller, 'get_orders'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/referrals', ['methods' => 'GET', 'callback' => [$referral_controller, 'get_my_referrals'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/catalog/products', ['methods' => 'GET', 'callback' => [$catalog_controller, 'get_products'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/catalog/products/(?P<id>[\d]+)', ['methods' => 'GET', 'callback' => [$catalog_controller, 'get_product'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/pages/(?P<slug>[a-zA-Z0-9-]+)', ['methods' => 'GET', 'callback' => [$page_controller, 'get_page'], 'permission_callback' => $permission_auth]);
    }
}