<?php
// FILE: includes/CannaRewards/CannaRewardsEngine.php

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
        // This version correctly runs on the 'init' hook to prevent timing issues.
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
        // "Wake up" services to register their event listeners.
        $this->container->get(Services\GamificationService::class);
        $this->container->get(Services\EconomyService::class);
    }
    
    private function init_wordpress_components() {
        AdminMenu::init();
        UserProfile::init();
        ProductMetabox::init();
        new AchievementMetabox();
        new CustomFieldMetabox();
        new TriggerMetabox();
        
        // Since this now runs on 'init', we can call the CPT registration functions directly.
        canna_register_rank_post_type();
        canna_register_achievement_post_type();
        canna_register_custom_field_post_type();
        canna_register_trigger_post_type();
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        register_activation_hook(CANNA_PLUGIN_FILE, [DB::class, 'activate']);
    }

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $v1_namespace = 'rewards/v1';
        $permission_public = '__return_true';
        $permission_auth   = fn() => is_user_logged_in();
        $permission_admin  = function(\WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            return wp_verify_nonce($nonce, 'wp_rest') && current_user_can('manage_options');
        };

        // All routes get their controller instances from the container.
        register_rest_route($v2_namespace, '/rules/conditions', [
            'methods' => 'GET',
            'callback' => [$this->container->get(Api\RulesController::class), 'get_conditions'],
            'permission_callback' => $permission_admin
        ]);
        register_rest_route($v2_namespace, '/unauthenticated/welcome-reward-preview', ['methods' => 'GET', 'callback' => [$this->container->get(Api\UnauthenticatedDataController::class), 'get_welcome_reward_preview'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/unauthenticated/claim', ['methods' => 'POST', 'callback' => [$this->container->get(Api\ClaimController::class), 'process_unauthenticated_claim'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/register-with-token', ['methods' => 'POST', 'callback' => [$this->container->get(Api\AuthController::class), 'register_with_token'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/register', ['methods' => 'POST', 'callback' => [$this->container->get(Api\AuthController::class), 'register_user'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/auth/login', ['methods' => 'POST', 'callback' => [$this->container->get(Api\AuthController::class), 'login_user'], 'permission_callback' => $permission_public]);
        register_rest_route($v1_namespace, '/password/request', ['methods' => 'POST', 'callback' => [$this->container->get(Api\AuthController::class), 'request_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($v1_namespace, '/password/reset', ['methods' => 'POST', 'callback' => [$this->container->get(Api\AuthController::class), 'perform_password_reset'], 'permission_callback' => $permission_public]);
        register_rest_route($v2_namespace, '/app/config', ['methods' => 'GET', 'callback' => [$this->container->get(Services\ConfigService::class), 'get_app_config'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/session', ['methods' => 'GET', 'callback' => function() { $user_service = $this->container->get(Services\UserService::class); $session_dto = $user_service->get_user_session_data(get_current_user_id()); return Api\ApiResponse::success((array) $session_dto); }, 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/actions/claim', ['methods' => 'POST', 'callback' => [$this->container->get(Api\ClaimController::class), 'process_claim'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/actions/redeem', ['methods' => 'POST', 'callback' => [$this->container->get(Api\RedeemController::class), 'process_redemption'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/profile', [['methods' => 'GET', 'callback' => [$this->container->get(Api\ProfileController::class), 'get_profile'], 'permission_callback' => $permission_auth], ['methods' => 'POST', 'callback' => [$this->container->get(Api\ProfileController::class), 'update_profile'], 'permission_callback' => $permission_auth]]);
        register_rest_route($v2_namespace, '/users/me/history', ['methods' => 'GET', 'callback' => [$this->container->get(Api\HistoryController::class), 'get_history'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/orders', ['methods' => 'GET', 'callback' => [$this->container->get(Api\OrdersController::class), 'get_orders'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/users/me/referrals', ['methods' => 'GET', 'callback' => [$this->container->get(Api\ReferralController::class), 'get_my_referrals'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/catalog/products', ['methods' => 'GET', 'callback' => [$this->container->get(Api\CatalogController::class), 'get_products'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/catalog/products/(?P<id>[\d]+)', ['methods' => 'GET', 'callback' => [$this->container->get(Api\CatalogController::class), 'get_product'], 'permission_callback' => $permission_auth]);
        register_rest_route($v2_namespace, '/pages/(?P<slug>[a-zA-Z0-9-]+)', ['methods' => 'GET', 'callback' => [$this->container->get(Api\PageController::class), 'get_page'], 'permission_callback' => $permission_auth]);
    }
}