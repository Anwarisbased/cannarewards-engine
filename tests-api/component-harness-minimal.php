<?php
/**
 * Minimal isolated component harness for testing.
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
    $input_data = isset($request_body['input']) ? $request_body['input'] : null;

    if (!$component_class || !$input_data) {
        throw new InvalidArgumentException('Missing "component" or "input" in request body.');
    }

    // Bootstrap WordPress minimally
    require_once dirname(__DIR__, 4) . '/wp-load.php';

    // Handle only the specific component we need for the test
    // Check multiple possible formats
    $target_class = 'CannaRewards\Commands\CreateUserCommandHandler';
    if ($component_class === $target_class || 
        $component_class === 'CannaRewards\\Commands\\CreateUserCommandHandler' ||
        $component_class === 'CannaRewards\\\\Commands\\\\CreateUserCommandHandler') {
        
        // Get container and dependencies properly
        $container = CannaRewards();
        
        // Get the component instance from the container (let DI handle dependencies)
        $handler = $container->get($target_class);
        
        // Include required classes for the command object
        $plugin_dir = dirname(__DIR__);
        
        // Include the command class
        if (file_exists($plugin_dir . '/includes/CannaRewards/Commands/CreateUserCommand.php')) {
            include_once $plugin_dir . '/includes/CannaRewards/Commands/CreateUserCommand.php';
        }
        
        // Include required value objects
        if (file_exists($plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/EmailAddress.php')) {
            include_once $plugin_dir . '/includes/CannaRewards/Domain/ValueObjects/EmailAddress.php';
        }
        
        // Create command object
        $email = \CannaRewards\Domain\ValueObjects\EmailAddress::fromString($input_data['email']);
        $command = new \CannaRewards\Commands\CreateUserCommand(
            $email,
            (string) (isset($input_data['password']) ? $input_data['password'] : ''),
            (string) (isset($input_data['firstName']) ? $input_data['firstName'] : ''),
            (string) (isset($input_data['lastName']) ? $input_data['lastName'] : ''),
            (string) (isset($input_data['phone']) ? $input_data['phone'] : ''),
            (bool) (isset($input_data['agreedToTerms']) ? $input_data['agreedToTerms'] : false),
            (bool) (isset($input_data['agreedToMarketing']) ? $input_data['agreedToMarketing'] : false),
            isset($input_data['referralCode']) ? $input_data['referralCode'] : null
        );
        
        // Execute and get result
        $result = $handler->handle($command);
        
        // Return success response
        echo json_encode(array('success' => true, 'data' => (array) $result));
        exit;
    }
    
    // If we get here, the component is not supported
    throw new InvalidArgumentException("Component not supported in isolated harness: " . $component_class . " (expected: " . $target_class . ")");

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
