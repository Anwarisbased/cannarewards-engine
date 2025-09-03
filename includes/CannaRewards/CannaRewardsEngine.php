<?php
namespace CannaRewards;

// Use statements for all the classes we will initialize.
use CannaRewards\Admin\AdminMenu;
use CannaRewards\Admin\UserProfile;
use CannaRewards\Admin\ProductMetabox;
use CannaRewards\Admin\AchievementMetabox;
use CannaRewards\Admin\CustomFieldMetabox;
use CannaRewards\Admin\TriggerMetabox; // ADDED THIS
use CannaRewards\Api\ApiManager;
use CannaRewards\Includes\DB;
use CannaRewards\Includes\Integrations;

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

    /**
     * Initializes all the static classes and instantiates the necessary objects.
     */
    public function init_classes() {
        ApiManager::init();
        AdminMenu::init();
        UserProfile::init();
        Integrations::init();
        ProductMetabox::init();
        new AchievementMetabox();
        new CustomFieldMetabox();
        new TriggerMetabox(); // ADDED THIS
    }

    /**
     * Setups up all the WordPress hooks and filters.
     */
    public function init_hooks() {
        // Hook in CPTs from our global functions file.
        add_action('init', 'canna_register_rank_post_type', 0);
        add_action('init', 'canna_register_achievement_post_type', 0);
        add_action('init', 'canna_register_custom_field_post_type', 0);
        add_action('init', 'canna_register_trigger_post_type', 0); // ADDED THIS
        
        // Activation hook must use the full namespaced class name as a string.
        register_activation_hook(CANNA_PLUGIN_FILE, ['CannaRewards\Includes\DB', 'activate']);
        
        // Utility hooks for clearing transients (cache).
        add_action('save_post_canna_rank', 'canna_clear_rank_cache');
        add_action('delete_post_canna_rank', 'canna_clear_rank_cache');

        add_action('save_post_canna_custom_field', 'canna_clear_custom_fields_cache');
        add_action('delete_post', function($post_id){
            if (get_post_type($post_id) === 'canna_custom_field') {
                canna_clear_custom_fields_cache();
            }
        });

        // ADDED THESE HOOKS
        add_action('save_post_canna_trigger', 'canna_clear_triggers_cache');
        add_action('delete_post', function($post_id){
            if (get_post_type($post_id) === 'canna_trigger') {
                canna_clear_triggers_cache();
            }
        });
    }
}