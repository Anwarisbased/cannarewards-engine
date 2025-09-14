<?php
// Test script to check if responders are working correctly
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Test the SuccessResponder
$responder = new \CannaRewards\Api\Responders\SuccessResponder(['test' => 'data']);
$response = $responder->toWpRestResponse();

// Output the response data
echo "Response data: ";
print_r($response->get_data());
echo "\nResponse status: " . $response->get_status() . "\n";