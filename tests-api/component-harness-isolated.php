<?php
/**
 * A direct execution harness for component-level testing with Playwright.
 * DANGER: For local development and testing ONLY.
 * This version bypasses WordPress autoloading issues.
 */

// Define ABSPATH if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__DIR__)))) . '/');
}

// 1. Basic Security Check
// Check for production environment
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
    http_response_code(403);
    echo json_encode(['error' => 'This script is disabled in production.']);
    exit;
}

// Bootstrap WordPress
require_once dirname(__DIR__, 4) . '/wp-load.php';

header('Content-Type: application/json');

try {
    // 2. Decode the request from Playwright
    $request_body = json_decode(file_get_contents('php://input'), true);
    $component_class = $request_body['component'] ?? null;
    $method_to_call = $request_body['method'] ?? 'handle'; // Default to 'handle' for commands
    $input_data = $request_body['input'] ?? null;

    if (!$component_class || !$input_data) {
        throw new InvalidArgumentException('Missing "component" or "input" in request body.');
    }

    // 3. Manual class loading to avoid conflicts
    // We'll manually include the required files and create instances
    
    // Load required classes manually
    require_once dirname(__DIR__) . '/includes/canna-core-functions.php';
    
    // Get the DI container
    $container = CannaRewards();
    
    // Manually create the component instance based on the class name
    $component_instance = null;
    
    switch ($component_class) {
        case 'CannaRewards\\Commands\\CreateUserCommandHandler':
            // Load required dependencies
            if (!class_exists('\\CannaRewards\\Commands\\CreateUserCommandHandler', false)) {
                require_once dirname(__DIR__) . '/includes/CannaRewards/Commands/CreateUserCommandHandler.php';
            }
            if (!class_exists('\\CannaRewards\\Commands\\CreateUserCommand', false)) {
                require_once dirname(__DIR__) . '/includes/CannaRewards/Commands/CreateUserCommand.php';
            }
            
            // Get dependencies from container
            $user_repository = $container->get('\\CannaRewards\\Repositories\\UserRepository');
            $cdp_service = $container->get('\\CannaRewards\\Services\\CDPService');
            $referral_service = $container->get('\\CannaRewards\\Services\\ReferralService');
            $eventBus = $container->get('\\CannaRewards\\Includes\\EventBusInterface');
            $configService = $container->get('\\CannaRewards\\Services\\ConfigService');
            
            // Create the component instance manually
            $component_instance = new \CannaRewards\Commands\CreateUserCommandHandler(
                $user_repository,
                $cdp_service,
                $referral_service,
                $eventBus,
                $configService
            );
            break;

        case 'CannaRewards\\Commands\\GrantPointsCommandHandler':
            // Load required dependencies
            if (!class_exists('\\CannaRewards\\Commands\\GrantPointsCommandHandler', false)) {
                require_once dirname(__DIR__) . '/includes/CannaRewards/Commands/GrantPointsCommandHandler.php';
            }
            if (!class_exists('\\CannaRewards\\Commands\\GrantPointsCommand', false)) {
                require_once dirname(__DIR__) . '/includes/CannaRewards/Commands/GrantPointsCommand.php';
            }
            
            // Get dependencies from container
            $points_repository = $container->get('\\CannaRewards\\Repositories\\PointsRepository');
            $user_repository = $container->get('\\CannaRewards\\Repositories\\UserRepository');
            $eventBus = $container->get('\\CannaRewards\\Includes\\EventBusInterface');
            
            // Create the component instance manually
            $component_instance = new \CannaRewards\Commands\GrantPointsCommandHandler(
                $points_repository,
                $user_repository,
                $eventBus
            );
            break;

        default:
            // Try to get from container as fallback
            $component_instance = $container->get($component_class);
            break;
    }
    
    // 4. Create the input object based on the component
    $input_object = null;
    switch ($component_class) {
        case 'CannaRewards\\Commands\\CreateUserCommandHandler':
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

        case 'CannaRewards\\Commands\\GrantPointsCommandHandler':
            $input_object = new \CannaRewards\Commands\GrantPointsCommand(
                (int) ($input_data['user_id'] ?? 0),
                (int) ($input_data['base_points'] ?? 0),
                (string) ($input_data['description'] ?? ''),
                (float) ($input_data['temp_multiplier'] ?? 1.0)
            );
            break;
        
        case 'CannaRewards\\Services\\UserService':
            // For services, the input is not a command object, but the direct arguments.
            // We pass them as an array.
            $input_object = $input_data;
            break;
        
        default:
            throw new InvalidArgumentException("No test harness logic defined for component: {$component_class}");
    }
    
    // 5. Execute the component's logic
    if ($component_class === 'CannaRewards\\Services\\UserService') {
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

