<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Create a simple test to see if the points are being awarded
echo "Testing points award...\n";

// Get the container
$container = require_once dirname(__DIR__) . '/includes/container.php';

// Get the event bus
$eventBus = $container->get(\CannaRewards\Includes\EventBusInterface::class);

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
            'product_id' => 204,
            'product_name' => 'Test Product'
        ]
    ]
]);

echo "Test completed.\n";
?>