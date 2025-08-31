<?php
/**
 * Plugin Name:       CannaRewards Engine
 * Plugin URI:        https://yourwebsite.com/
 * Description:       The all-in-one, self-reliant engine for the CannaRewards PWA.
 * Version:           5.1.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com/
 * Text Domain:       canna-rewards
 *
 * @package CannaRewards
 */

// Exit if accessed directly to prevent security vulnerabilities.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants for easy and reliable access to paths and files.
define('CANNA_PLUGIN_FILE', __FILE__);
define('CANNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CANNA_PLUGIN_VERSION', '5.1.0');

// =============================================================================
// 1. INCLUDE PLUGIN FILES
// =============================================================================

require_once CANNA_PLUGIN_DIR . 'includes/canna-core-functions.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-db.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-points-handler.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-achievement-handler.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-api-manager.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-integrations.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-custom-fields.php';
require_once CANNA_PLUGIN_DIR . 'includes/class-canna-cdp-handler.php'; // This was added in the sprint

require_once CANNA_PLUGIN_DIR . 'admin/class-canna-admin-menu.php';
require_once CANNA_PLUGIN_DIR . 'admin/class-canna-user-profile.php';
require_once CANNA_PLUGIN_DIR . 'admin/class-canna-product-metabox.php';
require_once CANNA_PLUGIN_DIR . 'admin/class-canna-achievement-metabox.php';

// =============================================================================
// 2. PLUGIN HOOKS
// =============================================================================

register_activation_hook(CANNA_PLUGIN_FILE, ['Canna_DB', 'activate']);

// =============================================================================
// 3. PLUGIN INITIALIZATION
// =============================================================================

function canna_rewards_run() {
    add_action('init', 'canna_register_rank_post_type', 0);
    add_action('init', 'canna_register_achievement_post_type', 0);

    Canna_API_Manager::init();
    Canna_Admin_Menu::init();
    Canna_User_Profile::init();
    Canna_Integrations::init();
    Canna_Custom_Fields::init();
    Canna_Product_Metabox::init();
    new Canna_Achievement_Metabox();
}
add_action('plugins_loaded', 'canna_rewards_run');

function canna_clear_rank_cache() {
    delete_transient('canna_rank_structure');
}
add_action('save_post_canna_rank', 'canna_clear_rank_cache');
add_action('delete_post', 'canna_clear_rank_cache');