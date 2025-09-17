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
    // Always get the component instance from the container
    $component_instance = $container->get($component_class);
    
    // 4. The Router: Now simplified. We build the input based on the component, then call the method.
    $input_object = null;
    switch ($component_class) {

        case \CannaRewards\Commands\CreateUserCommandHandler::class:
            $input_object = new \CannaRewards\Commands\CreateUserCommand(
                \CannaRewards\Domain\ValueObjects\EmailAddress::fromString($input_data['email']),
                \CannaRewards\Domain\ValueObjects\PlainTextPassword::fromString($input_data['password'] ?? ''),
                (string) ($input_data['firstName'] ?? ''),
                (string) ($input_data['lastName'] ?? ''),
                isset($input_data['phone']) ? \CannaRewards\Domain\ValueObjects\PhoneNumber::fromString($input_data['phone']) : null,
                (bool) ($input_data['agreedToTerms'] ?? false),
                (bool) ($input_data['agreedToMarketing'] ?? false),
                isset($input_data['referralCode']) ? \CannaRewards\Domain\ValueObjects\ReferralCode::fromString($input_data['referralCode']) : null
            );
            break;

        case \CannaRewards\Commands\GrantPointsCommandHandler::class:
            $input_object = new \CannaRewards\Commands\GrantPointsCommand(
                \CannaRewards\Domain\ValueObjects\UserId::fromInt((int) ($input_data['user_id'] ?? 0)),
                \CannaRewards\Domain\ValueObjects\Points::fromInt((int) ($input_data['base_points'] ?? 0)),
                (string) ($input_data['description'] ?? ''),
                (float) ($input_data['temp_multiplier'] ?? 1.0)
            );
            break;
        
        case \CannaRewards\Services\UserService::class:
            // For services, the input is not a command object, but the direct arguments.
            // We need to convert them to the proper types.
            if ($method_to_call === 'get_user_session_data') {
                // Special handling for get_user_session_data method
                $user_id_vo = \CannaRewards\Domain\ValueObjects\UserId::fromInt((int) ($input_data['user_id'] ?? 0));
                $input_object = [$user_id_vo];
            } else {
                // For other methods, pass as array
                $input_object = $input_data;
            }
            break;
        
        case \CannaRewards\Services\EconomyService::class:
            // For EconomyService, we need to create the proper command object
            if (isset($input_data['command'])) {
                switch ($input_data['command']) {
                    case 'RedeemRewardCommand':
                        // Include required classes
                        if (!class_exists('CannaRewards\\Domain\\ValueObjects\\UserId')) {
                            include_once dirname(__DIR__) . '/includes/CannaRewards/Domain/ValueObjects/UserId.php';
                        }
                        if (!class_exists('CannaRewards\\Domain\\ValueObjects\\ProductId')) {
                            include_once dirname(__DIR__) . '/includes/CannaRewards/Domain/ValueObjects/ProductId.php';
                        }
                        if (!class_exists('CannaRewards\\Commands\\RedeemRewardCommand')) {
                            include_once dirname(__DIR__) . '/includes/CannaRewards/Commands/RedeemRewardCommand.php';
                        }
                        
                        $input_object = new \CannaRewards\Commands\RedeemRewardCommand(
                            \CannaRewards\Domain\ValueObjects\UserId::fromInt((int) ($input_data['userId'] ?? 0)),
                            \CannaRewards\Domain\ValueObjects\ProductId::fromInt((int) ($input_data['productId'] ?? 0)),
                            $input_data['shippingDetails'] ?? []
                        );
                        break;
                    default:
                        throw new InvalidArgumentException("Unsupported command for EconomyService: {$input_data['command']}");
                }
            } else {
                throw new InvalidArgumentException("EconomyService requires a 'command' parameter");
            }
            break;
        
        default:
            throw new InvalidArgumentException("No test harness logic defined for component: {$component_class}");
    }
    
    // 5. Execute the component's logic
    if ($component_instance instanceof \CannaRewards\Services\UserService && $method_to_call === 'get_user_session_data') {
        // Special handling for UserService::get_user_session_data
        $result = $component_instance->$method_to_call($input_object[0]);
    } else if ($component_instance instanceof \CannaRewards\Services\UserService) {
        // Special handling for other service methods that take array args
        $result = call_user_func_array([$component_instance, $method_to_call], array_values($input_object));
    } else if ($component_instance instanceof \CannaRewards\Services\EconomyService && $input_object instanceof \CannaRewards\Commands\RedeemRewardCommand) {
        // Special handling for EconomyService with RedeemRewardCommand
        $result = $component_instance->handle($input_object);
    } else {
        // Default handling for command handlers
        $result = $component_instance->handle($input_object);
    }


    // 6. Send a successful result back to Playwright
    // DTOs need to be properly serialized for JSON
    if ($result instanceof \CannaRewards\DTO\SessionUserDTO) {
        // Special handling for SessionUserDTO to ensure proper serialization
        $response_data = [
            'id' => $result->id->toInt(),
            'firstName' => $result->firstName,
            'lastName' => $result->lastName,
            'email' => (string) $result->email,
            'points_balance' => $result->pointsBalance->toInt(),
            'rank' => [
                'key' => (string) $result->rank->key,
                'name' => $result->rank->name,
                'points' => $result->rank->pointsRequired->toInt(),
                'point_multiplier' => $result->rank->pointMultiplier
            ],
            'shipping' => $result->shippingAddress ? [
                'first_name' => $result->shippingAddress->firstName,
                'last_name' => $result->shippingAddress->lastName,
                'address_1' => $result->shippingAddress->address1,
                'city' => $result->shippingAddress->city,
                'state' => $result->shippingAddress->state,
                'postcode' => $result->shippingAddress->postcode
            ] : null,
            'referral_code' => null, // This would need to be fetched from user meta
            'onboarding_quest_step' => 0, // This would need to be fetched from user meta
            'feature_flags' => $result->featureFlags
        ];
        echo json_encode(['success' => true, 'data' => $response_data]);
    } else if ($result instanceof \CannaRewards\DTO\GrantPointsResultDTO) {
        // Special handling for GrantPointsResultDTO to ensure proper serialization
        $response_data = [
            'pointsEarned' => $result->pointsEarned->toInt(),
            'newPointsBalance' => $result->newPointsBalance->toInt()
        ];
        echo json_encode(['success' => true, 'data' => $response_data]);
    } else {
        // Default handling for other results
        echo json_encode(['success' => true, 'data' => (array) $result]);
    }

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