<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Create a simple test to see if the event is being broadcast
echo "Testing event broadcast...\n";

// Get the event bus
$container = require_once dirname(__DIR__) . '/includes/container.php';
$eventBus = $container->get(\CannaRewards\Includes\EventBusInterface::class);

// Add a listener to see if the event is being broadcast
$eventBus->listen('product_scanned', function($payload) {
    echo "Product scanned event received: " . json_encode($payload) . "\n";
});

// Simulate a product scan event
$eventBus->broadcast('product_scanned', [
    'user_snapshot' => [
        'identity' => [
            'user_id' => 1
        ]
    ],
    'is_first_scan' => false,
    'product_snapshot' => [
        'identity' => [
            'product_id' => 204
        ]
    ]
]);

echo "Test completed.\n";
?>