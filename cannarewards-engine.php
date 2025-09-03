<?php
/**
 * Plugin Name:       CannaRewards Engine
 * Plugin URI:        https://yourwebsite.com/
 * Description:       The all-in-one, self-reliant engine for the CannaRewards PWA.
 * Version:           2.0.0
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

// 1. Include the Composer Autoloader. This replaces all the old manual require statements.
// It will automatically find and load all our namespaced classes on demand.
require_once CANNA_PLUGIN_DIR . 'vendor/autoload.php';

// 2. Include the procedural functions file that doesn't have a class.
// This is the only manual include we need for our own code.
require_once CANNA_PLUGIN_DIR . 'includes/canna-core-functions.php';

// 3. Use our root namespace for the main engine class.
use CannaRewards\CannaRewardsEngine;

/**
 * The main function for returning the CannaRewardsEngine instance.
 * It ensures the plugin is a singleton, meaning it only runs once.
 */
function CannaRewards() {
    return CannaRewardsEngine::instance();
}

// Get the plugin running.
CannaRewards();