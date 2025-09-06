<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\UpdateProfileCommand;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Service (Command Bus & Data Fetcher)
 *
 * Handles dispatching user-related "write" actions to handlers and
 * serves as the primary source for fetching complex, structured user data objects.
 */
class UserService {
    private $command_map = [];
    private $cdp_service;
    private $action_log_service;
    private $referral_service; // This will be injected via setter to break circular dependency

    public function __construct(CDPService $cdp_service, ActionLogService $action_log_service) {
        $this->cdp_service = $cdp_service;
        $this->action_log_service = $action_log_service;
    }

    /**
     * Setter method to resolve the circular dependency with ReferralService.
     * This is called by the DI Container after both services are instantiated.
     */
    public function set_referral_service(ReferralService $referral_service): void {
        $this->referral_service = $referral_service;
    }
    
    public function registerCommandHandler(string $command_class, object $handler_instance): void {
        $this->command_map[$command_class] = $handler_instance;
    }

    public function handle($command) {
        $command_class = get_class($command);
        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for user command: {$command_class}");
        }
        $handler = $this->command_map[$command_class];

        // This is a form of "method injection" to provide the final circular dependency to the handler
        // right before it's executed. This keeps the handler's constructor clean.
        if (method_exists($handler, 'setReferralService')) {
            $handler->setReferralService($this->referral_service);
        }

        return $handler->handle($command);
    }
    
    /**
     * Gets the minimal, lightweight data needed for an authenticated session.
     * This is a "read" operation, so it remains in the service.
     */
    public function get_user_session_data( int $user_id ): array {
        $user = get_userdata($user_id);
        if (!$user) return [];

        $rank = get_user_current_rank($user_id);
        $shipping_address = [
            'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'shipping_last_name'  => get_user_meta($user_id, 'shipping_last_name', true),
            'shipping_address_1'  => get_user_meta($user_id, 'shipping_address_1', true),
            'shipping_city'       => get_user_meta($user_id, 'shipping_city', true),
            'shipping_state'      => get_user_meta($user_id, 'shipping_state', true),
            'shipping_postcode'   => get_user_meta($user_id, 'shipping_postcode', true),
        ];

        return [
            'id'             => $user_id,
            'firstName'      => $user->first_name,
            'lastName'       => $user->last_name,
            'email'          => $user->user_email,
            'points_balance' => get_user_points_balance($user_id),
            'rank'           => ['key' => $rank['key'] ?? 'member', 'name' => $rank['name'] ?? 'Member'],
            'shipping'       => $shipping_address,
            'referral_code'  => get_user_meta($user_id, '_canna_referral_code', true),
            'onboarding_quest_step' => (int) get_user_meta($user_id, '_onboarding_quest_step', true) ?: 1,
            'feature_flags'  => new \stdClass(),
        ];
    }
    
    /**
     * Gets the complete profile data for a user, including all custom fields.
     * This is a "read" operation, so it remains in the service.
     */
    public function get_full_profile_data( int $user_id ): array {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $custom_fields_definitions = canna_get_custom_fields_definitions();
        $custom_fields_values      = [];
        foreach ($custom_fields_definitions as $field) {
            $value = get_user_meta($user_id, $field['key'], true);
            if (!empty($value)) {
                $custom_fields_values[$field['key']] = $value;
            }
        }
        
        $shipping_address = [
            'first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'last_name' => get_user_meta($user_id, 'shipping_last_name', true),
            'address_1' => get_user_meta($user_id, 'shipping_address_1', true),
            'city' => get_user_meta($user_id, 'shipping_city', true),
            'state' => get_user_meta($user_id, 'shipping_state', true),
            'postcode' => get_user_meta($user_id, 'shipping_postcode', true),
        ];

        return [
            'lastName'                  => $user->last_name,
            'phone_number'              => get_user_meta($user_id, 'phone_number', true),
            'referral_code'             => get_user_meta($user_id, '_canna_referral_code', true),
            'shipping_address'          => $shipping_address,
            'unlocked_achievement_keys' => [], // This would come from AchievementRepository
            'custom_fields'             => [
                'definitions' => $custom_fields_definitions,
                'values'      => (object) $custom_fields_values,
            ],
        ];
    }

    /**
     * Gets the dynamic data needed for the main user dashboard.
     */
    public function get_user_dashboard_data( int $user_id ): array {
        return [
            'lifetime_points' => get_user_lifetime_points( $user_id ),
        ];
    }
}