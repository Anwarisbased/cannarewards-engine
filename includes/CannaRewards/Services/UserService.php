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
    private ?Repositories\OrderRepository $orderRepo = null;
    private ?Infrastructure\WordPressApiWrapper $wp = null;

    public function __construct(
        ContainerInterface $container, // Keep container for lazy-loading handlers/policies
        array $policy_map,
        RankService $rankService,
        CustomFieldRepository $customFieldRepo,
        UserRepository $userRepo,
        Repositories\OrderRepository $orderRepo = null,
        Infrastructure\WordPressApiWrapper $wp = null
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;
        $this->customFieldRepo = $customFieldRepo;
        $this->userRepo = $userRepo;
        $this->orderRepo = $orderRepo;
        $this->wp = $wp;
        
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
    
    public function get_current_user_session_data(): SessionUserDTO {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            throw new Exception("User not authenticated.", 401);
        }
        return $this->get_user_session_data($user_id);
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

    public function get_current_user_full_profile_data(): FullProfileDTO {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            throw new Exception("User not authenticated.", 401);
        }
        return $this->get_full_profile_data($user_id);
    }

    public function get_user_dashboard_data( int $user_id ): array {
        return [
            'lifetime_points' => $this->userRepo->getLifetimePoints( $user_id ),
        ];
    }
    
    public function request_password_reset(string $email): void {
        // <<<--- REFACTOR: Use the wrapper for all checks and actions
        if (!$this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->isEmail($email) || !$this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->emailExists($email)) {
            return;
        }

        $user = $this->userRepo->getUserCoreDataBy('email', $email);
        $token = $this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->getPasswordResetKey($user);

        if (is_wp_error($token)) {
            error_log('Could not generate password reset token for ' . $email);
            return;
        }
        
        // This logic is okay, as ConfigService uses the wrapper
        $options = $this->container->get(\CannaRewards\Services\ConfigService::class)->get_app_config();
        $base_url = !empty($options['settings']['brand_personality']['frontend_url']) ? rtrim($options['settings']['brand_personality']['frontend_url'], '/') : home_url();
        $reset_link = "$base_url/reset-password?token=$token&email=" . rawurlencode($email);

        $this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->sendMail($email, 'Your Password Reset Request', "Click to reset: $reset_link");
    }

    public function perform_password_reset(string $token, string $email, string $password): void {
        // <<<--- REFACTOR: Use the wrapper
        $user = $this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->checkPasswordResetKey($token, $email);
        if (is_wp_error($user)) {
             throw new Exception('Your password reset token is invalid or has expired.', 400);
        }
        $this->container->get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)->resetPassword($user, $password);
    }
    
    public function login(string $username, string $password): array {
        // Use WordPress REST API to login
        $request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $request->set_body_params([
            'username' => $username,
            'password' => $password
        ]);
        $response = rest_do_request($request);

        if ($response->is_error()) {
            throw new Exception('Could not generate authentication token after registration.');
        }

        return $response->get_data();
    }
}