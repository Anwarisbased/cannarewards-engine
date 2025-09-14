PART 1 of 2: Foundational Hardening & Eliminating All Inconsistencies
This part focuses on the non-negotiable, foundational improvements required to achieve perfect consistency and robustness. These changes solidify the existing architecture and eliminate the few remaining "code smells" and inconsistencies.
Item 1.1: Eradicate the Legacy Static Event Bus
Intention: To establish a single, consistent, and testable method for event-driven communication across the entire application, eliminating architectural ambiguity.
Explanation: You currently have two competing event systems:
The Correct Way: An injectable, interface-based system (EventBusInterface -> WordPressEventBus.php) managed by the DI container. This is modern, testable, and explicit.
The Legacy Way: A static Event class (Includes/Event.php) that acts as a global singleton. This is a code smell. It creates hidden dependencies, makes testing difficult (you can't easily mock a static class), and violates the principle of Dependency Inversion. The presence of both systems creates confusion for developers on which one to use. We must eliminate the legacy system entirely.
Implementation: This requires a meticulous search-and-replace across the codebase.
Identify Targets: Find every file that uses the static Event::listen() or Event::broadcast(). A simple codebase search for Event:: will reveal all instances.
Inject the Interface: In the constructor of every class that uses the static Event class, inject the EventBusInterface.
code
PHP
// --- BEFORE (in a service constructor) ---
public function __construct(DependencyA $a, DependencyB $b) {
    $this->dependencyA = $a;
    $this->dependencyB = $b;
    // Static, implicit dependency
    Event::listen('some_event', [$this, 'handler']); 
}

// --- AFTER ---
use CannaRewards\Includes\EventBusInterface;

private EventBusInterface $eventBus;

public function __construct(DependencyA $a, DependencyB $b, EventBusInterface $eventBus) {
    $this->dependencyA = $a;
    $this->dependencyB = $b;
    $this->eventBus = $eventBus; // Assign injected dependency
    // Use the explicit dependency
    $this->eventBus->listen('some_event', [$this, 'handler']);
}
Update DI Container: For every class you just modified, ensure the DI container (container.php) is correctly configured to pass in the EventBusInterface. PHP-DI's autowiring will handle most of this automatically, but explicit definitions may need updating.
Replace Broadcasts: Swap all Event::broadcast() calls with $this->eventBus->broadcast().
Delete the File: Once no files reference Event::, delete includes/CannaRewards/Includes/Event.php.
Item 1.2: Enforce Universal ApiResponse Usage
Intention: To guarantee that every single API response, success or error, has the exact same, predictable JSON structure, strengthening the API contract and simplifying frontend clients.
Explanation: The ApiResponse utility is a fantastic tool for standardization, but it's not used everywhere. Some controllers, like CatalogController, still manually create new WP_Error or new WP_REST_Response. This leads to subtle inconsistencies in the API's output format, which is a common source of frontend bugs. This must be a zero-tolerance policy.
Implementation: Audit every single controller method and refactor it to use the ApiResponse utility.
code
PHP
// File: includes/CannaRewards/Api/CatalogController.php

// --- BEFORE ---
public function get_product( WP_REST_Request $request ) {
    // ... logic ...
    if (!$product_data) {
        return new WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
    }
    return new WP_REST_Response( $product_data, 200 );
}

// --- AFTER ---
public function get_product( WP_REST_Request $request ) {
    // ... logic ...
    if (!$product_data) {
        // Use the standardized helper
        return ApiResponse::not_found('Product not found.');
    }
    // Use the standardized helper. Note: The `ApiResponse` class automatically
    // wraps the payload in a `data` key, so your frontend needs to expect that.
    // If you don't want the wrapper, modify ApiResponse or stick to WP_REST_Response.
    // For consistency, I recommend using the wrapper.
    return ApiResponse::success($product_data);
}
Crucial Sub-task: The ApiResponse::success() method wraps the payload in a {"success": true, "data": {...}} structure. The get_product method was previously returning the payload directly. You must decide on one format and enforce it everywhere. The wrapped format is generally better as it's more extensible.
Item 1.3: Eradicate Magic Strings with Constants
Intention: To eliminate the risk of typos in database keys (meta keys, option names) and provide a single source of truth for your data schema, improving maintainability and developer confidence.
Explanation: Your repositories are excellent, but they contain hardcoded strings like _canna_points_balance and _canna_current_rank_key. A typo in one of these strings (_canna_point_balance) would not be caught by any linter and would introduce a subtle, hard-to-debug bug. This is a classic problem solved by centralizing these strings as class constants.
Implementation:
Create a Constants Class: Create a new file for schema constants.
code
PHP
// File: includes/CannaRewards/Domain/MetaKeys.php (NEW)
<?php
namespace CannaRewards\Domain;

final class MetaKeys {
    // User Meta
    const POINTS_BALANCE     = '_canna_points_balance';
    const LIFETIME_POINTS    = '_canna_lifetime_points';
    const CURRENT_RANK_KEY   = '_canna_current_rank_key';
    const REFERRAL_CODE      = '_canna_referral_code';
    const REFERRED_BY_USER_ID = '_canna_referred_by_user_id';

    // Product Meta
    const POINTS_AWARD       = 'points_award';
    const POINTS_COST        = 'points_cost';
    const REQUIRED_RANK      = '_required_rank';

    // Option Keys
    const MAIN_OPTIONS       = 'canna_rewards_options';
}
Refactor Repositories and Services: Replace every hardcoded string with a reference to the new constants class.
code
PHP
// File: includes/CannaRewards/Repositories/UserRepository.php

// --- BEFORE ---
public function getPointsBalance(int $user_id): int {
    $balance = $this->wp->getUserMeta($user_id, '_canna_points_balance', true);
    return empty($balance) ? 0 : (int) $balance;
}

// --- AFTER ---
use CannaRewards\Domain\MetaKeys;

public function getPointsBalance(int $user_id): int {
    $balance = $this->wp->getUserMeta($user_id, MetaKeys::POINTS_BALANCE, true);
    return empty($balance) ? 0 : (int) $balance;
}
Repeat Everywhere: Do this for every repository, admin class (AdminMenu.php, ProductMetabox.php), and any other file that references a meta key or option name.

Item 1.5: Make DTOs and Value Objects Truly Immutable
Intention: To make the application's internal data contracts completely tamper-proof, eliminating an entire class of bugs caused by accidental state modification.
Explanation: Your DTOs (SessionUserDTO, RankDTO, etc.) are simple public property bags. This is good, but it means any part of the application can change a DTO's values after it has been created (e.g., $dto->points_balance = 0;). This can lead to unpredictable behavior. True DTOs should be immutable: once created, they cannot be changed.
Implementation:
Leverage PHP 8.1+ readonly properties. This is the cleanest and most modern approach.
code
PHP
// File: includes/CannaRewards/DTO/RankDTO.php

// --- BEFORE ---
final class RankDTO {
    public string $key;
    public string $name;
    // ...
}

// --- AFTER (assuming PHP 8.1+) ---
final class RankDTO {
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly int $points,
        public readonly float $point_multiplier
    ) {}
}
Refactor Instantiation: You will now need to create DTOs via their constructor.
Harden Value Objects: Your EmailAddress Value Object can be made even more robust by enforcing creation through a named constructor and making the real constructor private.
code
PHP
// File: includes/CannaRewards/Domain/ValueObjects/EmailAddress.php

// --- BEFORE ---
final class EmailAddress {
    private string $value;
    public function __construct(string $email) { /* ... validation ... */ }
}

// --- AFTER ---
final class EmailAddress {
    private function __construct(public readonly string $value) {} // private constructor with promoted property

    public static function fromString(string $email): self {
        if (!is_email($email)) { // Or use wrapper
            throw new InvalidArgumentException("Invalid email address provided.");
        }
        return new self(strtolower(trim($email)));
    }

    public function __toString(): string {
        return $this->value;
    }
}

// Usage now becomes:
// $email = EmailAddress::fromString('test@example.com');
This makes the intent clearer and the object guaranteed to be valid and immutable.
This concludes Part 1. These five items represent the most critical next steps to perfect the foundation of the application. Part 2 will cover advanced patterns, performance, and developer workflow enhancements.

PART 2 of 2: Advanced Patterns, Performance, and Workflow Optimization
This part moves beyond fixing inconsistencies and focuses on proactive enhancements that will pay dividends in scalability, developer velocity, and long-term project health.
Item 2.1: Implement the Responder Pattern for Perfect API Consistency
Intention: To completely decouple controllers from the HTTP layer, ensuring every API response for a given outcome (e.g., "Not Found") is 100% identical, and making controllers even simpler and more testable.
Explanation: Currently, your controllers (even with ApiResponse) are still responsible for knowing about HTTP concepts. A controller returns a WP_REST_Response or WP_Error, which are WordPress constructs. The Responder pattern inverts this. Controllers return simple, descriptive PHP objects that represent a business outcome (e.g., ResourceFound, ValidationFailed), and a single, centralized piece of middleware is responsible for turning those objects into actual HTTP responses. This is the final step in making controllers pure application logic.
Implementation:
Create Responder Interfaces and Classes:
code
PHP
// File: includes/CannaRewards/Api/Responders/ResponderInterface.php (NEW)
interface ResponderInterface {
    public function toWpRestResponse(): \WP_REST_Response;
}

// File: includes/CannaRewards/Api/Responders/SuccessResponder.php (NEW)
class SuccessResponder implements ResponderInterface {
    public function __construct(private array $data, private int $statusCode = 200) {}
    public function toWpRestResponse(): \WP_REST_Response {
        return new \WP_REST_Response(['success' => true, 'data' => $this->data], $this->statusCode);
    }
}

// File: includes/CannaRewards/Api/Responders/NotFoundResponder.php (NEW)
class NotFoundResponder implements ResponderInterface {
    public function __construct(private string $message = 'Resource not found.') {}
    public function toWpRestResponse(): \WP_REST_Response {
        $error = new \WP_Error('not_found', $this->message, ['status' => 404]);
        return rest_ensure_response($error);
    }
}
// ... create responders for ValidationFailed, Forbidden, etc.
Refactor a Controller:
code
PHP
// File: includes/CannaRewards/Api/CatalogController.php

// --- BEFORE ---
public function get_product( WP_REST_Request $request ) {
    // ... logic ...
    if (!$product_data) {
        return ApiResponse::not_found('Product not found.');
    }
    return ApiResponse::success($product_data);
}

// --- AFTER ---
use CannaRewards\Api\Responders\SuccessResponder;
use CannaRewards\Api\Responders\NotFoundResponder;

public function get_product( WP_REST_Request $request ): ResponderInterface { // Return the interface
    // ... logic ...
    if (!$product_data) {
        return new NotFoundResponder('Product not found.');
    }
    return new SuccessResponder($product_data);
}
Create the Middleware: Hook into rest_pre_serve_request to intercept the Responder object before WordPress tries to render it.
code
PHP
// In CannaRewardsEngine.php or a dedicated routing class
add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
    if ($result instanceof \CannaRewards\Api\Responders\ResponderInterface) {
        $response = $result->toWpRestResponse();
        $server->serve_request($response); // Manually serve the converted response
        return true; // Tell WordPress we've handled it
    }
    return $served; // Let WordPress handle it
}, 10, 4);
Item 2.2: Implement Caching at the Repository Layer
Intention: To dramatically improve API response times for frequently accessed, rarely changed data (like product details, rank structures, and achievement definitions) by reducing redundant database queries.
Explanation: Your architecture is perfectly suited for a caching layer. The repositories are the gatekeepers to the database, making them the ideal place to introduce caching logic. When a service asks a repository for data, the repository should first check its cache. If the data is present and not expired, it returns it instantly; otherwise, it queries the database and then stores the result in the cache for next time.
Implementation: Use the WordPressApiWrapper's transient methods within your repositories. You already do this in a few places; it should be applied universally.
code
PHP
// File: includes/CannaRewards/Repositories/ProductRepository.php

class ProductRepository {
    // ... constructor ...

    public function getProductDetails(int $productId): ?array { // Example method
        $cacheKey = "product_details_{$productId}";
        $cachedProduct = $this->wp->getTransient($cacheKey);

        if ($cachedProduct) {
            return $cachedProduct; // Cache HIT
        }

        // Cache MISS - do the expensive work
        $product = $this->wp->getProduct($productId);
        if (!$product) {
            return null;
        }
        
        $details = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'points_cost' => (int) $product->get_meta(MetaKeys::POINTS_COST),
            // ... etc
        ];

        // Store in cache for 1 hour
        $this->wp->setTransient($cacheKey, $details, HOUR_IN_SECONDS);

        return $details;
    }
}
Crucial Sub-task: Implement cache-busting logic. When a product is updated in the WordPress admin, you must hook into save_post_product to delete the relevant transient (delete_transient("product_details_{$productId}")). This ensures the cache is never stale.
Item 2.3: Introduce an Asynchronous Queue for Long-Running Tasks
Intention: To ensure fast API responses and improve system resilience by offloading non-critical, time-consuming tasks (like sending emails or complex achievement calculations) to a background process.
Explanation: When a user registers, your API call waits for a referral code to be generated, points to be calculated, CDP events to be sent, and potentially emails to be dispatched. This can make the API feel slow. An asynchronous queue (like Action Scheduler, which is built into WooCommerce) allows you to say, "The critical work is done. Schedule these other tasks to run in the background." The API can then immediately return a success response to the user.
Implementation:
Leverage Action Scheduler: Since you have WooCommerce, you already have a robust queueing library.
Create a "Job Handler" Service: This service will contain the logic that runs in the background.
code
PHP
// File: includes/CannaRewards/Jobs/PostRegistrationJob.php (NEW)
class PostRegistrationJob {
    private ReferralService $referralService;
    private CDPService $cdpService;

    public function __construct(ReferralService $referral, CDPService $cdp) {
        $this->referralService = $referral;
        $this->cdpService = $cdp;
        // Hook our job handler into Action Scheduler
        add_action('canna_run_post_registration_tasks', [$this, 'handle'], 10, 2);
    }

    public function handle(int $userId, string $firstName) {
        // This code runs in the background, not during the API request
        $this->referralService->generate_code_for_new_user($userId, $firstName);
        $this->cdpService->track($userId, 'user_created', ['signup_method' => 'password']);
    }
}
// Don't forget to instantiate this service in CannaRewardsEngine to register the hook.
Refactor the Command Handler to Dispatch the Job:
code
PHP
// File: includes/CannaRewards/Commands/CreateUserCommandHandler.php

// --- BEFORE ---
public function handle(CreateUserCommand $command): array {
    // ... creates user ...
    $this->referral_service->generate_code_for_new_user($user_id, $command->first_name);
    $this->cdp_service->track($user_id, 'user_created', /* ... */);
    return ['success' => true, /* ... */];
}

// --- AFTER ---
public function handle(CreateUserCommand $command): array {
    // Do ONLY the critical work synchronously
    $user_id = $this->user_repository->createUser(/* ... */);
    $this->user_repository->saveInitialMeta(/* ... */);

    // Offload the slow work to the background queue
    as_enqueue_async_action(
        'canna_run_post_registration_tasks', 
        ['userId' => $user_id, 'firstName' => $command->first_name], 
        'cannarewards-jobs'
    );

    return ['success' => true, 'message' => 'Registration successful.', 'userId' => $user_id];
}
Item 2.4: Introduce a Feature Flag System
Intention: To enable progressive rollouts, A/B testing, and "kill switch" functionality for new features without requiring a full code deployment.
Explanation: Your SessionUserDTO already has a placeholder for feature_flags. It's time to implement this properly. A feature flag system allows you to control which users see which features directly from the WordPress admin or based on user properties. For example: "Only show the new 'Wishlist' feature to users in the 'gold' rank" or "Roll out the new dashboard design to 10% of users."
Implementation:
Create a FeatureFlagService:
code
PHP
// File: includes/CannaRewards/Services/FeatureFlagService.php (NEW)
class FeatureFlagService {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) { $this->wp = $wp; }

    public function getFlagsForUser(int $userId): object {
        $flags = [];
        
        // Example: A simple flag controlled by a WordPress option
        $globalFlags = $this->wp->getOption('canna_feature_flags', []);
        if (!empty($globalFlags['new_dashboard_enabled'])) {
            $flags['dashboard_version'] = 'B';
        }

        // Example: A flag based on user rank
        $rank = $this->wp->getUserMeta($userId, MetaKeys::CURRENT_RANK_KEY, true);
        if ($rank === 'gold') {
            $flags['wishlist_enabled'] = true;
        }

        return (object) $flags; // Ensure it's an object
    }
}
Integrate with UserService:
code
PHP
// In UserService.php, inject FeatureFlagService
public function get_user_session_data( int $user_id ): SessionUserDTO {
    // ... other logic ...
    $session_dto->feature_flags = $this->featureFlagService->getFlagsForUser($user_id);
    return $session_dto;
}
Build a UI: Create a simple admin page (or use a plugin like "Flagship") to manage the values in the canna_feature_flags WordPress option, allowing non-developers to toggle features on and off for the entire user base.
Item 2.5: Centralize API Routing and Finalize the Engine
Intention: To clean up the main plugin bootstrap (CannaRewardsEngine.php) and move all API route definitions to a dedicated, self-contained class, adhering to the Single Responsibility Principle.
Explanation: CannaRewardsEngine.php is currently doing two jobs: bootstrapping the plugin and defining all API routes. As the number of routes grows, this will become messy. The routing definitions belong in their own dedicated Router class.
Implementation:
Create a Router class:
code
PHP
// File: includes/CannaRewards/Api/Router.php (NEW)
class Router {
    public function __construct(private ContainerInterface $container) {}

    public function registerRoutes(): void {
        add_action('rest_api_init', [$this, 'defineRoutes']);
    }

    public function defineRoutes(): void {
        $v2_namespace = 'rewards/v2';
        // ... all your register_rest_route calls and the create_route_callback factory method go here ...
    }

    private function create_route_callback(/*...*/) { /* ... */ }
}
Simplify the Engine:
code
PHP
// File: includes/CannaRewards/CannaRewardsEngine.php
class CannaRewardsEngine {
    // ...
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        add_action('init', [$this, 'init']);
    }

    public function init() {
        // ... other init logic ...
        
        // Get the router from the container and tell it to register the routes
        $router = $this->container->get(\CannaRewards\Api\Router::class);
        $router->registerRoutes();
        
        // ... instantiate event-driven services ...
    }
    // The register_rest_routes and create_route_callback methods are now GONE from this file.
}
This final change makes the engine's responsibilities cleaner and the API routes easier to manage as a distinct unit.