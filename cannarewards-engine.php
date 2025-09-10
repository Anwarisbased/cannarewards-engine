<?php
// FILE: cannarewards-engine.php

/**
 * Plugin Name:       CannaRewards Engine
 * Plugin URI:        https://yourwebsite.com/
 * Description:       The all-in-one, self-reliant engine for the CannaRewards PWA.
 * Version:           2.2.0
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
 * The main function that boots the CannaRewards application.
 */
function CannaRewards() {
    // --- CHANGE: The entire bootstrap process is now clean and simple ---
    // 1. Build the self-sufficient DI container from our new bootstrap file.
    $container = require CANNA_PLUGIN_DIR . 'includes/container.php';
    
    // 2. Ask the container for the main engine instance.
    // The CannaRewardsEngine class is now managed by the container,
    // and all of its dependencies are automatically resolved and injected.
    return $container->get(CannaRewards\CannaRewardsEngine::class);
}

// Get the plugin running.
CannaRewards();