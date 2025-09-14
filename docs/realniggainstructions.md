Phase 1: Modernize the Administrative Interface
Item 1.1: Refactor Admin Classes to be Object-Oriented Services
Intention: To bring the architectural quality of the Admin/ directory up to the same exceptional standard as the rest of the application, making it fully testable, dependency-managed, and free of WordPress's procedural style.
Explanation: Your application core is a masterpiece of modern, injectable, object-oriented design. In stark contrast, the Admin/ classes (AdminMenu, ProductMetabox, UserProfile) are still written in a legacy WordPress pattern, relying heavily on static methods and add_action calls from a static init() context. This makes them difficult to test and creates a jarring inconsistency in the codebase. We will refactor them into proper services that are instantiated by the DI container.
Implementation:
Step 1: Create a FieldFactory Service
Action: Create a new file.
Path: includes/CannaRewards/Admin/FieldFactory.php
Purpose: This service will encapsulate all raw HTML generation for form fields, decoupling the admin classes from presentation.
Content:
code
PHP
<?php
namespace CannaRewards\Admin;

final class FieldFactory {
    public function render_text_input(string $name, string $value, array $args = []): void {
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="%s" placeholder="%s" />',
            esc_attr($args['type'] ?? 'text'),
            esc_attr($args['id'] ?? $name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($args['class'] ?? 'regular-text'),
            esc_attr($args['placeholder'] ?? '')
        );
        if (!empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    // Add similar methods for `render_select`, `render_checkbox`, `render_textarea`
}
Step 2: Refactor AdminMenu into an Instantiable Service
Action: Modify includes/CannaRewards/Admin/AdminMenu.php.
Change: Convert all static methods to instance methods. Inject dependencies via the constructor. Let the init() method register the hooks.
code
PHP
<?php
namespace CannaRewards\Admin;

use CannaRewards\Infrastructure\WordPressApiWrapper;

final class AdminMenu {
    private const PARENT_SLUG = 'canna_rewards_settings';
    private WordPressApiWrapper $wp;
    private FieldFactory $fieldFactory;

    public function __construct(WordPressApiWrapper $wp, FieldFactory $fieldFactory) {
        $this->wp = $wp;
        $this->fieldFactory = $fieldFactory;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function add_admin_menu(): void {
        // ... logic ...
    }

    public function settings_init(): void {
        // ... all register_setting and add_settings_field calls,
        // but with callbacks pointing to `$this`, e.g., [$this, 'field_html_callback']
    }
    
    public function field_html_callback(array $args): void {
        $options = $this->wp->getOption('canna_rewards_options');
        $value = $options[$args['id']] ?? '';
        $this->fieldFactory->render_text_input(
            "canna_rewards_options[{$args['id']}]",
            $value,
            $args
        );
    }
    // ... convert all other methods from static to instance ...
}
Step 3: Repeat Refactoring for all Admin Classes
Action: Apply the exact same refactoring pattern (remove static, inject dependencies, use init() to add hooks) to the following files:
includes/CannaRewards/Admin/ProductMetabox.php
includes/CannaRewards/Admin/UserProfile.php
Note: Classes like AchievementMetabox are already instantiated with new, which is good. They should also be refactored to receive dependencies like FieldFactory via their constructor.
Step 4: Update the DI Container and Engine
Action: Modify includes/container.php.
Change: Add definitions for all the newly refactored admin classes.
code
PHP
// In container.php
\CannaRewards\Admin\FieldFactory::class => create(),
\CannaRewards\Admin\AdminMenu::class => autowire(),
\CannaRewards\Admin\ProductMetabox::class => autowire(),
\CannaRewards\Admin\UserProfile::class => autowire(),
// ... etc for other admin classes ...
Action: Modify includes/CannaRewards/CannaRewardsEngine.php.
Change: In the init_wordpress_components method, get the admin services from the container and initialize them.
code
PHP
// In CannaRewardsEngine.php
private function init_wordpress_components() {
    $this->container->get(\CannaRewards\Admin\AdminMenu::class)->init();
    $this->container->get(\CannaRewards\Admin\ProductMetabox::class)->init();
    $this->container->get(\CannaRewards\Admin\UserProfile::class)->init();
    
    // These were already non-static, so just ensure they are in the container
    $this->container->get(\CannaRewards\Admin\AchievementMetabox::class);
    // ...
}
Phase 2: Implement an Elite-Tier Authorization Layer
Item 2.1: Create a Dedicated API Authorization Policy System
Intention: To create a powerful, reusable, and explicit authorization system for the API, moving beyond simple capability checks and enabling complex business rules for endpoint access.
Explanation: Your current permission system uses simple closures in the routing definition (fn() => is_user_logged_in()). This is fine but not scalable. What happens when you need a rule like "A user can only view their own orders"? A dedicated Authorization Policy system, similar to your Command Policies, makes these rules first-class citizens of your application.
Implementation:
Step 1: Create the Authorization Policy Interface and Base Classes
Action: Create a new directory includes/CannaRewards/Api/Policies/.
Action: Create new files within this directory.
Path: includes/CannaRewards/Api/Policies/ApiPolicyInterface.php
code
PHP
<?php
namespace CannaRewards\Api\Policies;
use WP_REST_Request;

interface ApiPolicyInterface {
    public function can(WP_REST_Request $request): bool;
}
Path: includes/CannaRewards/Api/Policies/CanViewOwnResourcePolicy.php
code
PHP
<?php
namespace CannaRewards\Api\Policies;
use WP_REST_Request;

class CanViewOwnResourcePolicy implements ApiPolicyInterface {
    public function can(WP_REST_Request $request): bool {
        $route_user_id = (int) $request->get_param('user_id');
        $current_user_id = get_current_user_id();

        if ($current_user_id === 0) {
            return false; // Not logged in
        }

        // Admins can do anything
        if (user_can($current_user_id, 'manage_options')) {
            return true;
        }

        return $current_user_id === $route_user_id;
    }
}
Step 2: Refactor the RouteServiceProvider to use Policies
Action: Modify includes/CannaRewards/Api/RouteServiceProvider.php.
Change: Update the routing array to reference policy classes and update the callback factory to resolve and execute them.
code
PHP
// In RouteServiceProvider.php

// Example change in getRoutes() array
'/users/{user_id}/orders' => [ // Hypothetical new route
    'methods' => 'GET',
    'controller' => \CannaRewards\Api\OrdersController::class,
    'method' => 'get_user_orders',
    'permission' => \CannaRewards\Api\Policies\CanViewOwnResourcePolicy::class, // Reference the class
],

// Modify defineRoutes() to handle the policy class
public function defineRoutes(): void {
    // ... loop ...
        $permissionCallback = $this->getPermissionCallback($config['permission'] ?? 'public');
    // ...
        'permission_callback' => $permissionCallback,
    // ...
}

private function getPermissionCallback($permission): callable {
    if (is_string($permission) && class_exists($permission)) {
        // If it's a class name, return a closure that resolves and runs it
        return function (\WP_REST_Request $request) use ($permission) {
            /** @var ApiPolicyInterface $policy */
            $policy = $this->container->get($permission);
            return $policy->can($request);
        };
    }

    // Fallback to the old key-based system
    switch ($permission) {
        case 'auth': return fn() => is_user_logged_in();
        // ... etc ...
    }
}
This provides a clean, powerful, and testable way to manage all API endpoint authorization logic.

Final Phase: Elite-Tier Performance, Observability, and Developer Experience
This phase addresses the last remaining areas for significant improvement: optimizing database performance with a more sophisticated caching strategy, formalizing observability with structured logging, and enhancing developer workflow by centralizing configuration.
Item 1: Implement a Centralized, Cache-Aware SettingsRepository
Intention: To create a single, authoritative, type-safe, and performant source of truth for all application settings, eliminating scattered get_option calls and providing a robust caching layer for configuration data.
Evaluation: Currently, configuration is pulled directly from WordPress options within multiple services (ConfigService, UserService) and admin classes (AdminMenu). While the use of the WordPressApiWrapper is good, this approach has several drawbacks:
Repetitive DB Calls: Each get_option('canna_rewards_options') call can result in a database query.
No Type Safety: The return value is an array of mixed types, leading to frequent (int) casting and reliance on isset() checks. A typo in an option key (e.g., 'welcom_reward_product') is a runtime bug.
Scattered Logic: Different services need to know the specific keys of the options array, coupling them to the database schema of the settings page.
Prescriptive Implementation: We will create a SettingsRepository that fetches all options once per request, stores them in a strongly-typed SettingsDTO, and serves this DTO to the rest of the application.
Step 1: Create the SettingsDTO
Action: Create a new file.
Path: includes/CannaRewards/DTO/SettingsDTO.php
Content: This class is an immutable data contract for all application settings.
code
PHP
<?php
namespace CannaRewards\DTO;

final class SettingsDTO {
    public function __construct(
        // General
        public readonly string $frontendUrl,
        public readonly string $supportEmail,
        public readonly int $welcomeRewardProductId,
        public readonly int $referralSignupGiftId,
        public readonly string $referralBannerText,

        // Personality
        public readonly string $pointsName,
        public readonly string $rankName,
        public readonly string $welcomeHeaderText,
        public readonly string $scanButtonCta
        // Add theme settings if needed
    ) {}
}
Step 2: Create the SettingsRepository
Action: Create a new file.
Path: includes/CannaRewards/Repositories/SettingsRepository.php
Content: This repository is the sole gatekeeper to the canna_rewards_options data.
code
PHP
<?php
namespace CannaRewards\Repositories;

use CannaRewards\DTO\SettingsDTO;
use CannaRewards\Infrastructure\WordPressApiWrapper;
use CannaRewards\Domain\MetaKeys;

final class SettingsRepository {
    private ?SettingsDTO $settingsCache = null;

    public function __construct(private WordPressApiWrapper $wp) {}

    public function getSettings(): SettingsDTO {
        if ($this->settingsCache !== null) {
            return $this->settingsCache; // Return from in-request cache
        }

        $options = $this->wp->getOption(MetaKeys::MAIN_OPTIONS, []);
        
        $dto = new SettingsDTO(
            frontendUrl: $options['frontend_url'] ?? home_url(),
            supportEmail: $options['support_email'] ?? get_option('admin_email'),
            welcomeRewardProductId: (int) ($options['welcome_reward_product'] ?? 0),
            referralSignupGiftId: (int) ($options['referral_signup_gift'] ?? 0),
            referralBannerText: $options['referral_banner_text'] ?? '',
            pointsName: $options['points_name'] ?? 'Points',
            rankName: $options['rank_name'] ?? 'Rank',
            welcomeHeaderText: $options['welcome_header'] ?? 'Welcome, {firstName}',
            scanButtonCta: $options['scan_cta'] ?? 'Scan Product'
        );

        $this->settingsCache = $dto; // Cache for the remainder of the request
        return $dto;
    }
}
Step 3: Refactor Services to Use the SettingsRepository
Action: Modify any service that currently fetches options.
Path: includes/CannaRewards/Services/ConfigService.php (and others like UserService).
Change: Inject the SettingsRepository and use it to get the SettingsDTO.
code
PHP
// In ConfigService constructor
public function __construct(
    private RankService $rankService,
    private WordPressApiWrapper $wp,
    private SettingsRepository $settingsRepo // Inject the new repo
) {}

// In getWelcomeRewardProductId method
public function getWelcomeRewardProductId(): int {
    return $this->settingsRepo->getSettings()->welcomeRewardProductId;
}

// In get_app_config method
public function get_app_config(): array {
    $settings = $this->settingsRepo->getSettings();
    return [
        'settings' => [
            'brand_personality' => [
                'points_name'    => $settings->pointsName,
                'rank_name'      => $settings->rankName,
                // ... etc, pull from the DTO
            ],
        ],
        // ...
    ];
}
Note: This change will propagate through any class that needs settings, creating a clean, single dependency and eliminating direct knowledge of the underlying WordPress option keys.