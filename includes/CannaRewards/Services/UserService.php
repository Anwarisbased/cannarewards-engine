<?php
namespace CannaRewards\Services;

use CannaRewards\DTO\FullProfileDTO;
use CannaRewards\DTO\RankDTO;
use CannaRewards\DTO\SessionUserDTO;
use CannaRewards\DTO\ShippingAddressDTO;
use CannaRewards\Repositories\CustomFieldRepository;
use CannaRewards\Repositories\UserRepository;
use Exception;
use Psr\Container\ContainerInterface;

/**
 * User Service (Command Bus & Data Fetcher)
 */
final class UserService {
    private array $command_map = [];
    private ContainerInterface $container; // We still need this to instantiate handlers and policies
    private array $policy_map = [];
    private RankService $rankService;
    private CustomFieldRepository $customFieldRepo;
    private UserRepository $userRepo;

    public function __construct(
        ContainerInterface $container, // Keep container for lazy-loading handlers/policies
        array $policy_map,
        RankService $rankService,
        CustomFieldRepository $customFieldRepo,
        UserRepository $userRepo
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;
        $this->customFieldRepo = $customFieldRepo;
        $this->userRepo = $userRepo;
        
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
        $user_data = $this->userRepo->getUserCoreData($user_id);
        if (!$user_data) {
            throw new Exception("User with ID {$user_id} not found.");
        }

        $rank_dto = $this->rankService->getUserRank($user_id);

        $session_dto = new SessionUserDTO();
        $session_dto->id = $user_id;
        $session_dto->firstName = $user_data->first_name;
        $session_dto->lastName = $user_data->last_name;
        $session_dto->email = $user_data->user_email;
        $session_dto->points_balance = $this->userRepo->getPointsBalance($user_id);
        $session_dto->rank = $rank_dto;
        $session_dto->shipping = $this->userRepo->getShippingAddressArray($user_id);
        $session_dto->referral_code = $this->userRepo->getReferralCode($user_id);
        $session_dto->onboarding_quest_step = (int) $this->userRepo->getUserMeta($user_id, '_onboarding_quest_step', true) ?: 1;
        // THIS IS THE FIX: Ensure it's an object, not an array.
        $session_dto->feature_flags = new \stdClass();

        return $session_dto;
    }
    
    public function get_full_profile_data( int $user_id ): FullProfileDTO {
        $user_data = $this->userRepo->getUserCoreData($user_id);
        if (!$user_data) {
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
        
        $shipping_dto = $this->userRepo->getShippingAddressDTO($user_id);

        $profile_dto = new FullProfileDTO();
        $profile_dto->lastName = $user_data->last_name;
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
        return [
            'lifetime_points' => $this->userRepo->getLifetimePoints( $user_id ),
        ];
    }
}