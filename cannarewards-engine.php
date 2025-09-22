<?php
/**
 * Plugin Name:       CannaRewards Engine
 * Plugin URI:        https://yourwebsite.com/
 * Description:       The all-in-one, self-reliant engine for the CannaRewards PWA.
 * Version:           2.1.0
 * Author:            Anwar Isbased
 * Author URI:        https://yourwebsite.com/
 * Text Domain:       canna-rewards
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants.
define('CANNA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CANNA_PLUGIN_FILE', __FILE__);

// 1. Include the Composer Autoloader.
require_once CANNA_PLUGIN_DIR . 'vendor/autoload.php';

// 2. Include the procedural functions file.
require_once CANNA_PLUGIN_DIR . 'includes/canna-core-functions.php';

/**
 * The main function for returning the CannaRewards DI Container instance.
 * @return Psr\Container\ContainerInterface The DI container.
 */
function CannaRewards() {
    static $container = null;
    if (is_null($container)) {
        // Build the self-sufficient DI container from our bootstrap file.
        $container = require CANNA_PLUGIN_DIR . 'includes/container.php';
        
        // Use the container to create the main engine instance, which hooks everything into WordPress.
        $container->get(\CannaRewards\CannaRewardsEngine::class);
    }
    return $container;
}

// Get the plugin running.
add_action('plugins_loaded', 'CannaRewards');

// Activation hook
register_activation_hook(__FILE__, function() {
    // This will be called when the plugin is activated
    require_once CANNA_PLUGIN_DIR . 'includes/canna-core-functions.php';
    \CannaRewards\Includes\DB::activate();
});