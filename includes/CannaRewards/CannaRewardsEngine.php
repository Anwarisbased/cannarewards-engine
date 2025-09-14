<?php
namespace CannaRewards;

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
        // Initialize admin services from the container
        $this->container->get(\CannaRewards\Admin\AdminMenu::class)->init();
        $this->container->get(\CannaRewards\Admin\ProductMetabox::class)->init();
        $this->container->get(\CannaRewards\Admin\UserProfile::class)->init();
        
        // These were already non-static, so just ensure they are in the container
        $this->container->get(\CannaRewards\Admin\AchievementMetabox::class);
        $this->container->get(\CannaRewards\Admin\CustomFieldMetabox::class);
        $this->container->get(\CannaRewards\Admin\TriggerMetabox::class);
        
        canna_register_rank_post_type();
        canna_register_achievement_post_type();
        canna_register_custom_field_post_type();
        canna_register_trigger_post_type();
        
        // Get the router from the container and tell it to register the routes
        $router = $this->container->get(\CannaRewards\Api\Router::class);
        $router->registerRoutes();
        
        register_activation_hook(CANNA_PLUGIN_FILE, [DB::class, 'activate']);
    }
}