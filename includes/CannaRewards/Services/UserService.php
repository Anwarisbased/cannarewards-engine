<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\UpdateProfileCommand;
use CannaRewards\DTO\RankDTO;
use CannaRewards\DTO\SessionUserDTO;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Service (Command Bus & Data Fetcher)
 */
class UserService {
    private array $command_map = [];
    private CDPService $cdp_service;
    private ActionLogService $action_log_service;
    private ?ReferralService $referral_service = null;
    private array $policy_map = [];
    private \CannaRewards\Container\DIContainer $container;
    private RankService $rankService;

    public function __construct(
        CDPService $cdp_service,
        ActionLogService $action_log_service,
        \CannaRewards\Container\DIContainer $container,
        array $policy_map,
        RankService $rankService
    ) {
        $this->cdp_service = $cdp_service;
        $this->action_log_service = $action_log_service;
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;
    }

    public function set_referral_service(ReferralService $referral_service): void {
        $this->referral_service = $referral_service;
    }
    
    public function registerCommandHandler(string $command_class, object $handler_instance): void {
        $this->command_map[$command_class] = $handler_instance;
    }

    public function handle($command) {
        $command_class = get_class($command);
        
        $policies_for_command = $this->policy_map[$command_class] ?? [];
        foreach ($policies_for_command as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command);
        }

        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for user command: {$command_class}");
        }
        $handler = $this->command_map[$command_class];

        if (method_exists($handler, 'setReferralService')) {
            $handler->setReferralService($this->referral_service);
        }

        return $handler->handle($command);
    }
    
    /**
     * Gets the minimal, lightweight data needed for an authenticated session.
     *
     * @return SessionUserDTO A strongly-typed object instead of a magic array.
     */
    public function get_user_session_data( int $user_id ): SessionUserDTO {
        $user = get_userdata($user_id);
        if (!$user) {
            throw new Exception("User with ID {$user_id} not found.");
        }

        $rank_dto = $this->rankService->getUserRank($user_id);

        $shipping_address = [
            'shipping_first_name' => get_user_meta($user_id, 'shipping_first_name', true),
            'shipping_last_name'  => get_user_meta($user_id, 'shipping_last_name', true),
            'shipping_address_1'  => get_user_meta($user_id, 'shipping_address_1', true),
            'shipping_city'       => get_user_meta($user_id, 'shipping_city', true),
            'shipping_state'      => get_user_meta($user_id, 'shipping_state', true),
            'shipping_postcode'   => get_user_meta($user_id, 'shipping_postcode', true),
        ];

        $session_dto = new SessionUserDTO();
        $session_dto->id = $user_id;
        $session_dto->firstName = $user->first_name;
        $session_dto->lastName = $user->last_name;
        $session_dto->email = $user->user_email;
        $session_dto->points_balance = get_user_points_balance($user_id);
        $session_dto->rank = $rank_dto;
        $session_dto->shipping = $shipping_address;
        $session_dto->referral_code = get_user_meta($user_id, '_canna_referral_code', true) ?: null;
        $session_dto->onboarding_quest_step = (int) get_user_meta($user_id, '_onboarding_quest_step', true) ?: 1;
        $session_dto->feature_flags = new \stdClass();

        return $session_dto;
    }
    
    /**
     * Gets the complete profile data for a user, including all custom fields.
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
            'unlocked_achievement_keys' => [],
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