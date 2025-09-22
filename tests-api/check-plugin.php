<?php
// Simple test script to check if plugin is active
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// Check if plugin is active
if (is_plugin_active('cannarewards-engine/cannarewards-engine.php')) {
    echo "CannaRewards plugin is active\n";
} else {
    echo "CannaRewards plugin is NOT active\n";
}

// Check if CannaRewards function exists
if (function_exists('CannaRewards')) {
    echo "CannaRewards function exists\n";
} else {
    echo "CannaRewards function does NOT exist\n";
}
?>