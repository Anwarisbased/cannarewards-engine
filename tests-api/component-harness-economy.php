<?php
/**
 * Minimal isolated component harness for EconomyService testing.
 * This version completely bypasses WordPress autoloading to avoid class conflicts.
 */

// Security check
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    http_response_code(403);
    echo json_encode(array('error' => 'This script is disabled in production.'));
    exit;
}

header('Content-Type: application/json');

try {
    // Decode request
    $request_body = json_decode(file_get_contents('php://input'), true);
    $component_class = isset($request_body['component']) ? $request_body['component'] : null;
    $method_to_call = isset($request_body['method']) ? $request_body['method'] : 'handle';
    $input_data = isset($request_body['input']) ? $request_body['input'] : null;

    if (!$component_class || !$input_data) {
        throw new InvalidArgumentException('Missing "component", "method", or "input" in request body.');
    }

    // Bootstrap WordPress minimally
    require_once dirname(__DIR__, 4) . '/wp-load.php';

    // Handle EconomyService
    if ($component_class === 'CannaRewards\\Services\\EconomyService' || 
        $component_class === 'CannaRewards\\\\Services\\\\EconomyService') {
        
        // Get container and dependencies properly
        $container = CannaRewards();
        
        // Get the component instance from the container (let DI handle dependencies)
        $service = $container->get('CannaRewards\\Services\\EconomyService');
        
        // Include required classes for the command object
        $plugin_dir = dirname(__DIR__);
        
        // Include the command class
        if (file_exists($plugin_dir . '/includes/CannaRewards/Commands/RedeemRewardCommand.php')) {
            include_once $plugin_dir . '/includes/CannaRewards/Commands/RedeemRewardCommand.php';
        }
        
        // Include required value objects
        if (file_exists($plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/UserId.php')) {
            include_once $plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/UserId.php';
        }
        
        if (file_exists($plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/ProductId.php')) {
            include_once $plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/ProductId.php';
        }
        
        // Create command object based on input data
        if (isset($input_data['command']) && $input_data['command'] === 'RedeemRewardCommand') {
            $command = new \CannaRewards\Commands\RedeemRewardCommand(
                \CannaRewards\Domain\ValueObjects\UserId::fromInt((int) ($input_data['userId'] ?? 0)),
                \CannaRewards\Domain\ValueObjects\ProductId::fromInt((int) ($input_data['productId'] ?? 0)),
                $input_data['shippingDetails'] ?? []
            );
            
            // Execute and get result
            $result = $service->handle($command);
            
            // Return success response
            echo json_encode(array('success' => true, 'data' => (array) $result));
            exit;
        }
        
        throw new InvalidArgumentException("Unsupported command for EconomyService");
    }
    
    // If we get here, the component is not supported
    throw new InvalidArgumentException("Component not supported in isolated harness: " . $component_class);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error'   => get_class($e),
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
        'trace'    => $e->getTraceAsString()
    ));
}

exit;
