<?php
/**
 * A direct execution harness for component-level testing with Playwright.
 * DANGER: For local development and testing ONLY.
 */

// 1. Basic Security & Bootstrap
require_once dirname(__DIR__, 4) . '/wp-load.php';

if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    http_response_code(403);
    echo json_encode(['error' => 'This script is disabled in production.']);
    exit;
}

header('Content-Type: application/json');

// 2. Get the DI Container
$container = CannaRewards();

try {
    // 3. Decode the request from Playwright
    $request_body = json_decode(file_get_contents('php://input'), true);
    $component_class = $request_body['component'] ?? null;
    $method_to_call = $request_body['method'] ?? 'handle'; // Default to 'handle' for commands
    $input_data = $request_body['input'] ?? null;

    if (!$component_class || !$input_data) {
        throw new InvalidArgumentException('Missing "component" or "input" in request body.');
    }

    // Check if class is already defined to avoid conflicts
    if (!class_exists($component_class, false)) {
        // Get the component instance from the container
        $component_instance = $container->get($component_class);
    } else {
        // If class exists, try to get it from the container anyway
        $component_instance = $container->get($component_class);
    }
    
    // 4. The Router: Now simplified. We build the input based on the component, then call the method.
    $input_object = null;
    switch ($component_class) {

        case \CannaRewards\Commands\CreateUserCommandHandler::class:
            $input_object = new \CannaRewards\Commands\CreateUserCommand(
                new \CannaRewards\Domain\ValueObjects\EmailAddress($input_data['email']),
                (string) ($input_data['password'] ?? ''),
                (string) ($input_data['firstName'] ?? ''),
                (string) ($input_data['lastName'] ?? ''),
                (string) ($input_data['phone'] ?? ''),
                (bool) ($input_data['agreedToTerms'] ?? false),
                (bool) ($input_data['agreedToMarketing'] ?? false),
                $input_data['referralCode'] ?? null
            );
            break;

        case \CannaRewards\Commands\GrantPointsCommandHandler::class:
            $input_object = new \CannaRewards\Commands\GrantPointsCommand(
                (int) ($input_data['user_id'] ?? 0),
                (int) ($input_data['base_points'] ?? 0),
                (string) ($input_data['description'] ?? ''),
                (float) ($input_data['temp_multiplier'] ?? 1.0)
            );
            break;
        
        case \CannaRewards\Services\UserService::class:
            // For services, the input is not a command object, but the direct arguments.
            // We pass them as an array.
            $input_object = $input_data;
            break;
        
        default:
            throw new InvalidArgumentException("No test harness logic defined for component: {$component_class}");
    }
    
    // 5. Execute the component's logic
    if ($component_instance instanceof \CannaRewards\Services\UserService) {
        // Special handling for service methods that take array args
        $result = call_user_func_array([$component_instance, $method_to_call], $input_object);
    } else {
        // Default handling for command handlers
        $result = $component_instance->handle($input_object);
    }


    // 6. Send a successful result back to Playwright
    // DTOs need to be cast to an array for proper JSON serialization
    echo json_encode(['success' => true, 'data' => (array) $result]);

} catch (Exception $e) {
    // 7. Send any exceptions back to Playwright for failure assertions
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => get_class($e),
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
        'trace'   => $e->getTraceAsString()
    ]);
}

exit;