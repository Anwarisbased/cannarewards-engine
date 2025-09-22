<?php
// Simple test script to check API endpoints
$wp_root = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
require_once $wp_root . '/wp-load.php';

// Test if the CannaRewards plugin is active
if (function_exists('CannaRewards')) {
    echo "CannaRewards function exists\n";
} else {
    echo "CannaRewards function does NOT exist\n";
}

// Test if the REST API routes are registered
$routes = rest_get_server()->get_routes();
$found = false;
foreach ($routes as $route => $handlers) {
    if (strpos($route, 'rewards') !== false) {
        echo "Found rewards route: $route\n";
        $found = true;
    }
}

if (!$found) {
    echo "No rewards routes found\n";
}

// Print all routes for debugging
echo "\nAll registered routes:\n";
foreach ($routes as $route => $handlers) {
    echo "$route\n";
}
?>