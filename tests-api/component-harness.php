<?php
/**
 * A direct execution harness for component-level testing with Playwright.
 * DANGER: For local development and testing ONLY.
 */

// 1. Basic Security & Bootstrap
// This loads the WordPress environment and our application's DI container.
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
    $input_data = $request_body['input'] ?? null;

    if (!$component_class || !$input_data) {
        throw new InvalidArgumentException('Missing "component" or "input" in request body.');
    }

    // 4. The Router: Map component names to executable logic
    switch ($component_class) {

        case \CannaRewards\Commands\CreateUserCommandHandler::class:
            // Get the handler instance from the container
            $handler = $container->get($component_class);
            // Create the command DTO from the input, including the EmailAddress Value Object
            $command = new \CannaRewards\Commands\CreateUserCommand(
                new \CannaRewards\Domain\ValueObjects\EmailAddress($input_data['email']),
                (string) ($input_data['password'] ?? ''),
                (string) ($input_data['firstName'] ?? ''),
                (string) ($input_data['lastName'] ?? ''),
                (string) ($input_data['phone'] ?? ''),
                (bool) ($input_data['agreedToTerms'] ?? false),
                (bool) ($input_data['agreedToMarketing'] ?? false),
                $input_data['referralCode'] ?? null
            );
            // Execute the component's logic
            $result = $handler->handle($command);
            break;

        case \CannaRewards\Commands\GrantPointsCommandHandler::class:
            $handler = $container->get($component_class);
            $command = new \CannaRewards\Commands\GrantPointsCommand(
                (int) ($input_data['user_id'] ?? 0),
                (int) ($input_data['base_points'] ?? 0),
                (string) ($input_data['description'] ?? ''),
                (float) ($input_data['temp_multiplier'] ?? 1.0)
            );
            $result = $handler->handle($command);
            break;
        
        default:
            throw new InvalidArgumentException("No test harness logic defined for component: {$component_class}");
    }

    // 5. Send a successful result back to Playwright
    echo json_encode(['success' => true, 'data' => $result]);

} catch (Exception $e) {
    // 6. Send any exceptions back to Playwright for failure assertions
    http_response_code(400); // Bad Request is a good default for test failures
    echo json_encode([
        'success' => false,
        'error'   => get_class($e),
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
    ]);
}

exit;