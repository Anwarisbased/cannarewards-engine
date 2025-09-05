<?php
namespace CannaRewards;

// Use statements for all the classes we will initialize.
use CannaRewards\Admin\AdminMenu;
use CannaRewards\Admin\UserProfile;
use CannaRewards\Admin\ProductMetabox;
use CannaRewards\Admin\AchievementMetabox;
use CannaRewards\Admin\CustomFieldMetabox;
use CannaRewards\Admin\TriggerMetabox;
use CannaRewards\Api\AuthController;
use CannaRewards\Api\CatalogController;
use CannaRewards\Api\ClaimController;
use CannaRewards\Api\RedeemController;
use CannaRewards\Api\PageController;
use CannaRewards\Api\HistoryController;
use CannaRewards\Api\OrdersController;
use CannaRewards\Api\ProfileController;
use CannaRewards\Api\ReferralController;
use CannaRewards\Includes\DB;
use CannaRewards\Includes\Integrations;
use CannaRewards\Services\ConfigService;
use CannaRewards\Services\UserService;

final class CannaRewardsEngine {

    private static $instance;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>CannaRewards Engine Warning:</strong> WooCommerce is not installed or active. The plugin will not function.</p></div>';
            });
            return;
        }
        
        $this->init_classes();
        $this->init_hooks();
    }

    public function init_classes() {
        AdminMenu::init();
        UserProfile::init();
        Integrations::init();
        ProductMetabox::init();
        new AchievementMetabox();
        new CustomFieldMetabox();
        new TriggerMetabox();
    }

    public function init_hooks() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('init', 'canna_register_rank_post_type', 0);
        add_action('init', 'canna_register_achievement_post_type', 0);
        add_action('init', 'canna_register_custom_field_post_type', 0);
        add_action('init', 'canna_register_trigger_post_type', 0);
        
        register_activation_hook(CANNA_PLUGIN_FILE, ['CannaRewards\Includes\DB', 'activate']);
        
        add_action('save_post_canna_rank', 'canna_clear_rank_cache');
        add_action('delete_post_canna_rank', 'canna_clear_rank_cache');

        add_action('save_post_canna_custom_field', 'canna_clear_custom_fields_cache');
        add_action('delete_post', function($post_id){
            if (get_post_type($post_id) === 'canna_custom_field') {
                canna_clear_custom_fields_cache();
            }
        });

        add_action('save_post_canna_trigger', 'canna_clear_triggers_cache');
        add_action('delete_post', function($post_id){
            if (get_post_type($post_id) === 'canna_trigger') {
                canna_clear_triggers_cache();
            }
        });
    }

    public function register_rest_routes() {
        $v2_namespace = 'rewards/v2';
        $v1_namespace = 'rewards/v1'; // For legacy support.
        $permission_public = '__return_true';
        $permission_auth   = function () { return is_user_logged_in(); };

        // --- Instantiate Controllers & Services ---
        $auth_controller     = new AuthController();
        $catalog_controller  = new CatalogController();
        $claim_controller    = new ClaimController();
        $redeem_controller   = new RedeemController();
        $page_controller     = new PageController();
        $history_controller  = new HistoryController();
        $orders_controller   = new OrdersController();
        $profile_controller  = new ProfileController();
        $referral_controller = new ReferralController();
        $user_service        = new UserService();
        $config_service      = new ConfigService();

        // --- V2 Authentication Endpoints ---
        register_rest_route($v2_namespace, '/auth/register', [
            'methods'  => 'POST',
            'callback' => [$auth_controller, 'register_user'],
            'permission_callback' => $permission_public,
        ]);
        register_rest_route($v2_namespace, '/auth/login', [
            'methods'  => 'POST',
            'callback' => [$auth_controller, 'login_user'],
            'permission_callback' => $permission_public,
        ]);

        // --- V1 Password Reset Endpoints ---
        register_rest_route($v1_namespace, '/password/request', [
            'methods'  => 'POST',
            'callback' => [$auth_controller, 'request_password_reset'],
            'permission_callback' => $permission_public,
        ]);
        register_rest_route($v1_namespace, '/password/reset', [
            'methods'  => 'POST',
            'callback' => [$auth_controller, 'perform_password_reset'],
            'permission_callback' => $permission_public,
        ]);

        // --- V2 App & Session Endpoints ---
        register_rest_route($v2_namespace, '/app/config', [
            'methods'  => 'GET',
            'callback' => [$config_service, 'get_app_config'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/users/me/session', [
            'methods'  => 'GET',
            'callback' => function() use ($user_service) {
                return $user_service->get_user_session_data(get_current_user_id());
            },
            'permission_callback' => $permission_auth,
        ]);

        // --- V2 Actions Endpoints ---
        register_rest_route($v2_namespace, '/actions/claim', [
            'methods'  => 'POST',
            'callback' => [$claim_controller, 'process_claim'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/actions/redeem', [
            'methods'  => 'POST',
            'callback' => [$redeem_controller, 'process_redemption'],
            'permission_callback' => $permission_auth,
        ]);

        // --- V2 User Profile & Data Endpoints ---
        register_rest_route($v2_namespace, '/users/me/dashboard', [
            'methods'  => 'GET',
            'callback' => function() use ($user_service) {
                return $user_service->get_user_dashboard_data(get_current_user_id());
            },
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/users/me/profile', [
            [
                'methods'  => 'GET',
                'callback' => [$profile_controller, 'get_profile'],
                'permission_callback' => $permission_auth,
            ],
            [
                'methods'  => 'POST',
                'callback' => [$profile_controller, 'update_profile'],
                'permission_callback' => $permission_auth,
            ],
        ]);
        register_rest_route($v2_namespace, '/users/me/history', [
            'methods'  => 'GET',
            'callback' => [$history_controller, 'get_history'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/users/me/orders', [
            'methods'  => 'GET',
            'callback' => [$orders_controller, 'get_orders'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/users/me/referrals', [
            'methods'  => 'GET',
            'callback' => [$referral_controller, 'get_my_referrals'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/users/me/referrals/nudge', [
            'methods'  => 'POST',
            'callback' => [$referral_controller, 'get_nudge_options'],
            'permission_callback' => $permission_auth,
        ]);

        // --- V2 Catalog Endpoints (Secure Proxy) ---
        register_rest_route($v2_namespace, '/catalog/products', [
            'methods'  => 'GET',
            'callback' => [$catalog_controller, 'get_products'],
            'permission_callback' => $permission_auth,
        ]);
        register_rest_route($v2_namespace, '/catalog/products/(?P<id>[\d]+)', [
            'methods'  => 'GET',
            'callback' => [$catalog_controller, 'get_product'],
            'permission_callback' => $permission_auth,
        ]);

        // --- V2 Pages Endpoint ---
        register_rest_route($v2_namespace, '/pages/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'  => 'GET',
            'callback' => [$page_controller, 'get_page'],
            'permission_callback' => $permission_auth,
        ]);
    }
}