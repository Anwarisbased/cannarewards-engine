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
 * The main function for returning the CannaRewardsEngine instance.
 * It builds the DI container and boots the application.
 */
function CannaRewards() {
    // This is the new bootstrap process.
    // 1. Build the container from our new, smart bootstrap file.
    $container = require CANNA_PLUGIN_DIR . 'includes/container.php';
    
    // 2. Ask the container for the main engine instance.
    // The CannaRewardsEngine class itself is now managed by the container.
    return $container->get(CannaRewards\CannaRewardsEngine::class);
}

// Get the plugin running.
CannaRewards();