Understood. Here is the first part of a highly prescriptive, verbose, and granular implementation plan designed to be executed by an agentic coding system.
The overall goal of this two-part plan is to elevate the codebase from its current excellent state to one of world-class quality, consistency, and robustness by systematically eliminating all remaining architectural ambiguities and implementing advanced, scalable patterns.
PART 1 of 2: Foundational Unification and Modernization
This phase focuses on two critical areas: unifying the API routing layer into a single, authoritative source of truth, and completely modernizing the administrative interface to match the architectural rigor of the application's core.
Phase 1: Unify the API Routing Layer
Item 1.1: Consolidate Route Definitions into a Single Service Provider
Intention: To eliminate ambiguity and establish a single, clear, and authoritative class responsible for all aspects of API route registration, adhering strictly to the Single Responsibility Principle.
Explanation: The current implementation has split routing responsibilities between two classes: Api/Router.php (which hooks into WordPress) and Routing/RouteRegistrar.php (which contains a static array of route definitions). This separation is unnecessary and creates confusion. We will merge these responsibilities into a single, cleanly-named RouteServiceProvider class, making the routing system self-contained and easier to manage.
Implementation:
Step 1: Create the new RouteServiceProvider
Action: Create a new file.
Path: includes/CannaRewards/Api/RouteServiceProvider.php
Content: This class will combine the logic from both Router and RouteRegistrar.
code
PHP
<?php
namespace CannaRewards\Api;

use Psr\Container\ContainerInterface;
use CannaRewards\Routing\RouteRegistrar; // We'll use this temporarily

/**
 * The single source of truth for registering all API routes.
 */
final class RouteServiceProvider {
    private const API_NAMESPACE = 'rewards/v2';

    public function __construct(private ContainerInterface $container) {}

    /**
     * Hooks the route registration into WordPress.
     */
    public function registerRoutes(): void {
        add_action('rest_api_init', [$this, 'defineRoutes']);
    }

    /**
     * Defines and registers all application routes with WordPress.
     */
    public function defineRoutes(): void {
        $routes = $this->getRoutes();

        foreach ($routes as $endpoint => $config) {
            $method = $config['methods'];
            $controllerClass = $config['controller'];
            $callbackMethod = $config['method'];
            $permission = $this->getPermissionCallback($config['permission'] ?? 'public');
            $formRequestClass = $config['form_request'] ?? null;

            register_rest_route(self::API_NAMESPACE, $endpoint, [
                'methods' => $method,
                'callback' => $this->createRouteCallback($controllerClass, $callbackMethod, $formRequestClass),
                'permission_callback' => $permission
            ]);
        }
    }

    /**
     * A factory that wraps controller callbacks to enable Form Request injection and centralized error handling.
     */
    private function createRouteCallback(string $controllerClass, string $methodName, ?string $formRequestClass = null): callable {
        return function (\WP_REST_Request $request) use ($controllerClass, $methodName, $formRequestClass) {
            try {
                $controller = $this->container->get($controllerClass);
                $args = [];

                if ($formRequestClass) {
                    $formRequest = new $formRequestClass($request);
                    $args[] = $formRequest;
                } else {
                    $args[] = $request;
                }
                
                $result = call_user_func_array([$controller, $methodName], $args);
                
                // NEW: Allow controllers to return Responder objects
                if ($result instanceof \CannaRewards\Api\Responders\ResponderInterface) {
                    return $result; // Pass the responder to the middleware
                }
                
                // Fallback for controllers not yet using Responders
                return $result;

            } catch (\CannaRewards\Api\Exceptions\ValidationException $e) {
                $error = new \WP_Error('validation_failed', $e->getMessage(), ['status' => 422, 'errors' => $e->getErrors()]);
                return rest_ensure_response($error);
            } catch (\Exception $e) {
                $statusCode = $e->getCode() && is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
                $error = new \WP_Error('internal_error', $e->getMessage(), ['status' => $statusCode]);
                return rest_ensure_response($error);
            }
        };
    }
    
    /**
     * Defines the array of all application routes.
     * This logic is moved from the old RouteRegistrar.
     */
    private function getRoutes(): array {
         return [
            // Session routes
            '/users/me/session' => [
                'methods' => 'GET',
                'controller' => \CannaRewards\Api\SessionController::class,
                'method' => 'get_session_data',
                'permission' => 'auth',
            ],

            // Authentication routes
            '/auth/register' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'register_user',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RegisterUserRequest::class,
            ],
            
            '/auth/register-with-token' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'register_with_token',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RegisterWithTokenRequest::class,
            ],
            
            '/auth/login' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'login_user',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\LoginFormRequest::class,
            ],
            
            '/auth/request-password-reset' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'request_password_reset',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\RequestPasswordResetRequest::class,
            ],
            
            '/auth/perform-password-reset' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\AuthController::class,
                'method' => 'perform_password_reset',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\PerformPasswordResetRequest::class,
            ],

            // Action routes
            '/actions/claim' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ClaimController::class,
                'method' => 'process_claim',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\ClaimRequest::class,
            ],
            
            '/actions/redeem' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\RedeemController::class,
                'method' => 'process_redemption',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\RedeemRequest::class,
            ],

            // Unauthenticated routes
            '/unauthenticated/claim' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ClaimController::class,
                'method' => 'process_unauthenticated_claim',
                'permission' => 'public',
                'form_request' => \CannaRewards\Api\Requests\UnauthenticatedClaimRequest::class,
            ],

            // User profile routes
            '/users/me/profile' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ProfileController::class,
                'method' => 'update_profile',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\UpdateProfileRequest::class,
            ],

            // Referral routes
            '/users/me/referrals/nudge' => [
                'methods' => 'POST',
                'controller' => \CannaRewards\Api\ReferralController::class,
                'method' => 'get_nudge_options',
                'permission' => 'auth',
                'form_request' => \CannaRewards\Api\Requests\NudgeReferralRequest::class,
            ],

            // Legacy routes
            '/users/me/orders' => [
                'methods' => 'GET',
                'controller' => \CannaRewards\Api\OrdersController::class,
                'method' => 'get_orders',
                'permission' => 'auth',
            ],
        ];
    }

    /**
     * Returns the correct permission callback for a given key.
     */
    private function getPermissionCallback(string $permission): callable {
        switch ($permission) {
            case 'auth':
                return fn() => is_user_logged_in();
            case 'admin':
                return fn() => current_user_can('manage_options');
            case 'public':
            default:
                return '__return_true';
        }
    }
}
Step 2: Update the DI Container
Action: Modify.
Path: includes/container.php
Change: Replace the definition for the old Router with the new RouteServiceProvider.
code
PHP
// --- BEFORE ---
Router::class => create(Router::class)
    ->constructor(get(ContainerInterface::class)),

// --- AFTER ---
\CannaRewards\Api\RouteServiceProvider::class => create(\CannaRewards\Api\RouteServiceProvider::class)
    ->constructor(get(ContainerInterface::class)),
Step 3: Update the Engine
Action: Modify.
Path: includes/CannaRewards/CannaRewardsEngine.php
Change: Tell the engine to use the new RouteServiceProvider.
code
PHP
// --- BEFORE ---
// Get the router from the container and tell it to register the routes
$router = $this->container->get(Router::class);
$router->registerRoutes();

// --- AFTER ---
// Get the router from the container and tell it to register the routes
$router = $this->container->get(\CannaRewards\Api\RouteServiceProvider::class);
$router->registerRoutes();
Step 4: Delete Obsolete Files
Action: Delete File.
Path: includes/CannaRewards/Api/Router.php
Action: Delete Directory.
Path: includes/CannaRewards/Routing/ (This contains RouteRegistrar.php)
Phase 2: Modernize the Administrative Interface
Item 2.1: Abstract Admin Field Generation with a FieldFactory
Intention: To decouple the Admin UI classes from raw HTML generation, making them cleaner, more declarative, testable, and consistent with the object-oriented nature of the rest of the application.
Explanation: Classes like AdminMenu and ProductMetabox currently echo and printf blocks of HTML. This is a fragile, procedural approach. We will create a FieldFactory service responsible for rendering these HTML fields. The admin classes will then call this factory, describing what they want to render, not how to render it. This also requires making the static-heavy admin classes into proper, instantiable objects.
Implementation:
Step 1: Create the FieldFactory service
Action: Create a new file.
Path: includes/CannaRewards/Admin/FieldFactory.php
Content:
code
PHP
<?php
namespace CannaRewards\Admin;

/**
 * A service responsible for rendering standard HTML form fields for the WordPress admin.
 */
final class FieldFactory {
    public function render_text_input(string $id, string $name, string $value, array $args = []): void {
        $type = $args['type'] ?? 'text';
        $class = $args['class'] ?? 'regular-text';
        $placeholder = $args['placeholder'] ?? '';
        $description = $args['description'] ?? '';

        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            esc_attr($type),
            esc_attr($id),
            esc_attr($name),
            esc_attr($value),
            esc_attr($class),
            esc_attr($placeholder)
        );

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_select(string $id, string $name, string $current_value, array $options, array $args = []): void {
        $description = $args['description'] ?? '';
        $default_option = $args['default_option'] ?? '-- Select --';
        
        printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($name));
        if ($default_option) {
            echo '<option value="">' . esc_html($default_option) . '</option>';
        }

        foreach ($options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current_value, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        if ($description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }
}
Step 2: Refactor AdminMenu to be a non-static, injectable class
Action: Modify.
Path: includes/CannaRewards/Admin/AdminMenu.php
Change: Convert all static methods and properties to instance methods and properties. Inject dependencies (WordPressApiWrapper, FieldFactory) via the constructor.
code
PHP
// --- BEFORE ---
class AdminMenu {
    const PARENT_SLUG = 'canna_rewards_settings';
    private static ?WordPressApiWrapper $wp = null;
    public static function init() { /* ... */ }
    public static function add_admin_menu() { /* ... */ }
    // ... all other methods are static ...
}

// --- AFTER ---
<?php
namespace CannaRewards\Admin;

use CannaRewards\Infrastructure\WordPressApiWrapper;

class AdminMenu {
    const PARENT_SLUG = 'canna_rewards_settings';

    private WordPressApiWrapper $wp;
    private FieldFactory $fieldFactory;

    public function __construct(WordPressApiWrapper $wp, FieldFactory $fieldFactory) {
        $this->wp = $wp;
        $this->fieldFactory = $fieldFactory;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_post_canna_generate_codes', [$this, 'handle_code_generation']);
    }

    public function add_admin_menu() {
        // Unchanged, but now uses $this
    }

    public function settings_init() {
        // All calls to `[self::class, '...']` become `[$this, '...']`
        // ...
        add_settings_field('frontend_url', 'PWA Frontend URL', [$this, 'field_html_callback'], /* ... */);
        // ...
    }
    
    // ... other methods converted to public instance methods ...

    public function field_html_callback($args) {
        $options = $this->wp->getOption('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        
        // Delegate rendering to the factory
        $this->fieldFactory->render_text_input(
            $args['id'],
            "canna_rewards_options[{$args['id']}]",
            $value,
            [
                'type' => $args['type'] ?? 'text',
                'placeholder' => $args['placeholder'] ?? '',
                'description' => $args['description'] ?? ''
            ]
        );
    }

    public function field_select_product_callback($args) {
        if (!function_exists('wc_get_products')) { echo '<p>WooCommerce is not active.</p>'; return; }
        $options = $this->wp->getOption('canna_rewards_options');
        $value = $options[$args['id']] ?? '';

        $products = wc_get_products(['status' => 'publish', 'limit' => -1]);
        $product_options = [];
        foreach ($products as $product) {
            $product_options[$product->get_id()] = $product->get_name();
        }

        // Delegate rendering to the factory
        $this->fieldFactory->render_select(
            $args['id'],
            "canna_rewards_options[{$args['id']}]",
            $value,
            $product_options,
            [
                'description' => $args['description'],
                'default_option' => '-- Select a Reward --'
            ]
        );
    }

    // ... handle_code_generation also becomes an instance method, getting the wrapper from $this->wp
    public function handle_code_generation() {
        // ...
        $wp = $this->wp;
        // ...
    }
}
Step 3: Update the DI Container for Admin Classes
Action: Modify.
Path: includes/container.php
Change: Add definitions for the new FieldFactory and the refactored AdminMenu.
code
PHP
// Add these definitions to your container
\CannaRewards\Admin\FieldFactory::class => create(),

\CannaRewards\Admin\AdminMenu::class => create()
    ->constructor(
        get(\CannaRewards\Infrastructure\WordPressApiWrapper::class),
        get(\CannaRewards\Admin\FieldFactory::class)
    ),
Step 4: Update the Engine to Instantiate Admin Classes
Action: Modify.
Path: includes/CannaRewards/CannaRewardsEngine.php
Change: Instead of calling static init methods, get the admin class instances from the container and call their init methods.
code
PHP
// --- BEFORE ---
private function init_wordpress_components() {
    AdminMenu::init();
    UserProfile::init();
    ProductMetabox::init();
    // ...
}

// --- AFTER ---
private function init_wordpress_components() {
    $this->container->get(\CannaRewards\Admin\AdminMenu::class)->init();

    // Note: You will need to apply the same non-static refactoring pattern
    // to UserProfile and ProductMetabox for this to work fully.
    // For now, let's assume they are also refactored.
    $this->container->get(\CannaRewards\Admin\UserProfile::class)->init();
    $this->container->get(\CannaRewards\Admin\ProductMetabox::class)->init();

    new AchievementMetabox(); // This was already an instance, so it's fine.
    new CustomFieldMetabox();
    new TriggerMetabox();
    // ...
}