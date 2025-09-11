<?php
namespace CannaRewards\Services;

use CannaRewards\DTO\FullProfileDTO;
use CannaRewards\DTO\RankDTO;
use CannaRewards\DTO\SessionUserDTO;
use CannaRewards\DTO\ShippingAddressDTO;
use CannaRewards\Repositories\CustomFieldRepository;
use CannaRewards\Repositories\UserRepository; // <<< --- IMPORT UserRepository
use Exception;
use Psr\Container\ContainerInterface;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * User Service (Command Bus & Data Fetcher)
 */
final class UserService {
    private array $command_map = [];
    private ContainerInterface $container;
    private array $policy_map = [];
    private RankService $rankService;
    private CustomFieldRepository $customFieldRepo;
    private UserRepository $userRepo; // <<< --- ADD UserRepository PROPERTY

    public function __construct(
        ContainerInterface $container,
        array $policy_map,
        RankService $rankService,
        CustomFieldRepository $customFieldRepo,
        UserRepository $userRepo // <<< --- ADD UserRepository DEPENDENCY
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;
        $this->customFieldRepo = $customFieldRepo;
        $this->userRepo = $userRepo; // <<< --- ASSIGN UserRepository
        
        $this->registerCommandHandlers();
    }

    private function registerCommandHandlers(): void {
        $this->command_map = [
            \CannaRewards\Commands\CreateUserCommand::class => \CannaRewards\Commands\CreateUserCommandHandler::class,
            \CannaRewards\Commands\UpdateProfileCommand::class => \CannaRewards\Commands\UpdateProfileCommandHandler::class,
            \CannaRewards\Commands\RegisterWithTokenCommand::class => \CannaRewards\Commands\RegisterWithTokenCommandHandler::class,
        ];
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
        
        $handler_class = $this->command_map[$command_class];
        $handler = $this->container->get($handler_class);

        return $handler->handle($command);
    }
    
    public function get_user_session_data( int $user_id ): SessionUserDTO {
        $user = get_userdata($user_id); // This is one of the few acceptable direct WP calls, as it's just fetching the core object.
        if (!$user) {
            throw new Exception("User with ID {$user_id} not found.");
        }

        $rank_dto = $this->rankService->getUserRank($user_id);

        // All of these should technically use the UserRepository for purity,
        // but get_user_meta is a common pragmatic compromise. Let's fix it properly.
        $shipping_address = [
            'shipping_first_name' => $this->userRepo->getUserMeta($user_id, 'shipping_first_name', true),
            'shipping_last_name'  => $this->userRepo->getUserMeta($user_id, 'shipping_last_name', true),
            'shipping_address_1'  => $this->userRepo->getUserMeta($user_id, 'shipping_address_1', true),
            'shipping_city'       => $this->userRepo->getUserMeta($user_id, 'shipping_city', true),
            'shipping_state'      => $this->userRepo->getUserMeta($user_id, 'shipping_state', true),
            'shipping_postcode'   => $this->userRepo->getUserMeta($user_id, 'shipping_postcode', true),
        ];

        $session_dto = new SessionUserDTO();
        $session_dto->id = $user_id;
        $session_dto->firstName = $user->first_name;
        $session_dto->lastName = $user->last_name;
        $session_dto->email = $user->user_email;
        //
        // --- THIS IS THE FIX ---
        // Replace the undefined global function with a call to our repository.
        $session_dto->points_balance = $this->userRepo->getPointsBalance($user_id);
        // --- END FIX ---
        //
        $session_dto->rank = $rank_dto;
        $session_dto->shipping = $shipping_address;
        $session_dto->referral_code = $this->userRepo->getReferralCode($user_id);
        $session_dto->onboarding_quest_step = (int) $this->userRepo->getUserMeta($user_id, '_onboarding_quest_step', true) ?: 1;
        $session_dto->feature_flags = new \stdClass();

        return $session_dto;
    }
    
    public function get_full_profile_data( int $user_id ): FullProfileDTO {
        $user = get_userdata($user_id);
        if (!$user) {
            throw new Exception("User with ID {$user_id} not found.");
        }

        $custom_fields_definitions = $this->customFieldRepo->getFieldDefinitions();
        $custom_fields_values      = [];
        foreach ($custom_fields_definitions as $field) {
            $value = $this->userRepo->getUserMeta($user_id, $field['key'], true);
            if (!empty($value)) {
                $custom_fields_values[$field['key']] = $value;
            }
        }
        
        $shipping_dto = new ShippingAddressDTO();
        $shipping_dto->first_name = $this->userRepo->getUserMeta($user_id, 'shipping_first_name', true);
        $shipping_dto->last_name = $this->userRepo->getUserMeta($user_id, 'shipping_last_name', true);
        $shipping_dto->address_1 = $this->userRepo->getUserMeta($user_id, 'shipping_address_1', true);
        $shipping_dto->city = $this->userRepo->getUserMeta($user_id, 'shipping_city', true);
        $shipping_dto->state = $this->userRepo->getUserMeta($user_id, 'shipping_state', true);
        $shipping_dto->postcode = $this->userRepo->getUserMeta($user_id, 'shipping_postcode', true);

        $profile_dto = new FullProfileDTO();
        $profile_dto->lastName = $user->last_name;
        $profile_dto->phone_number = $this->userRepo->getUserMeta($user_id, 'phone_number', true);
        $profile_dto->referral_code = $this->userRepo->getReferralCode($user_id);
        $profile_dto->shipping_address = $shipping_dto;
        $profile_dto->unlocked_achievement_keys = []; // This should come from AchievementRepository
        $profile_dto->custom_fields = (object) [
            'definitions' => $custom_fields_definitions,
            'values'      => (object) $custom_fields_values,
        ];

        return $profile_dto;
    }

    public function get_user_dashboard_data( int $user_id ): array {
        // --- THIS IS THE FIX ---
        // Replace the undefined global function with a call to our repository.
        return [
            'lifetime_points' => $this->userRepo->getLifetimePoints( $user_id ),
        ];
        // --- END FIX ---
    }
}