<?php
namespace CannaRewards\Services;

use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Domain\ValueObjects\PhoneNumber;
use CannaRewards\Domain\ValueObjects\ReferralCode;
use CannaRewards\DTO\FullProfileDTO;
use CannaRewards\DTO\RankDTO;
use CannaRewards\DTO\SessionUserDTO;
use CannaRewards\DTO\ShippingAddressDTO;
use CannaRewards\Repositories\CustomFieldRepository;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Repositories\OrderRepository;
use CannaRewards\Infrastructure\WordPressApiWrapper;
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
    private ?OrderRepository $orderRepo = null;
    private ?WordPressApiWrapper $wp = null;

    public function __construct(
        ContainerInterface $container, // Keep container for lazy-loading handlers/policies
        array $policy_map,
        RankService $rankService,
        CustomFieldRepository $customFieldRepo,
        UserRepository $userRepo,
        OrderRepository $orderRepo = null,
        WordPressApiWrapper $wp = null
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
            
            // Check which type of policy this is and call it appropriately
            if ($policy instanceof \CannaRewards\Policies\AuthorizationPolicyInterface) {
                // Authorization policies need a user ID and the command
                // For now, we'll pass a dummy user ID for registration commands
                $user_id = new \CannaRewards\Domain\ValueObjects\UserId(0);
                $policy->check($user_id, $command);
            } elseif ($policy instanceof \CannaRewards\Policies\ValidationPolicyInterface) {
                // Validation policies need specific values from the command
                if ($command instanceof \CannaRewards\Commands\CreateUserCommand && 
                    $policy instanceof \CannaRewards\Policies\EmailAddressMustBeUniquePolicy) {
                    // For email uniqueness, pass the email address from the command
                    $policy->check($command->email);
                } else {
                    // For other validation policies, pass the entire command for now
                    // This might need to be refined based on specific policy requirements
                    $policy->check($command);
                }
            }
        }

        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for user command: {$command_class}");
        }
        
        $handler_class = $this->command_map[$command_class];
        $handler = $this->container->get($handler_class);

        return $handler->handle($command);
    }
    
    public function get_user_session_data(UserId $userId): SessionUserDTO {
        $user_data = $this->userRepo->getUserCoreData($userId);
        if (!$user_data) {
            throw new Exception("User with ID {$userId->toInt()} not found.");
        }

        $rank_dto = $this->rankService->getUserRank($userId);
        $referral_code = $this->userRepo->getReferralCode($userId);

        $session_dto = new SessionUserDTO(
            id: $userId,
            firstName: $user_data->first_name,
            lastName: $user_data->last_name,
            email: \CannaRewards\Domain\ValueObjects\EmailAddress::fromString($user_data->user_email),
            pointsBalance: \CannaRewards\Domain\ValueObjects\Points::fromInt($this->userRepo->getPointsBalance($userId)),
            rank: $rank_dto,
            shippingAddress: $this->userRepo->getShippingAddressDTO($userId),
            referralCode: $referral_code,
            featureFlags: new \stdClass()
        );

        return $session_dto;
    }
    
    public function get_current_user_session_data(): SessionUserDTO {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            throw new Exception("User not authenticated.", 401);
        }
        return $this->get_user_session_data(UserId::fromInt($user_id));
    }
    
    public function get_full_profile_data(UserId $userId): FullProfileDTO {
        $user_data = $this->userRepo->getUserCoreData($userId);
        if (!$user_data) {
            throw new Exception("User with ID {$userId->toInt()} not found.");
        }

        $custom_fields_definitions = $this->customFieldRepo->getFieldDefinitions();
        $custom_fields_values      = [];
        foreach ($custom_fields_definitions as $field) {
            $value = $this->userRepo->getUserMeta($userId, $field['key'], true);
            if (!empty($value)) {
                $custom_fields_values[$field['key']] = $value;
            }
        }
        
        $shipping_dto = $this->userRepo->getShippingAddressDTO($userId);

        // Get phone number and referral code from user meta, converting to Value Objects
        $phone_meta = $this->userRepo->getUserMeta($userId, 'phone_number', true);
        $referral_code_meta = $this->userRepo->getUserMeta($userId, 'referral_code', true);
        
        $profile_dto = new FullProfileDTO(
            firstName: $user_data->first_name,
            lastName: $user_data->last_name,
            phoneNumber: !empty($phone_meta) ? PhoneNumber::fromString($phone_meta) : null,
            referralCode: !empty($referral_code_meta) ? ReferralCode::fromString($referral_code_meta) : null,
            shippingAddress: $shipping_dto,
            unlockedAchievementKeys: [], // This should come from AchievementRepository
            customFields: (object) [
                'definitions' => $custom_fields_definitions,
                'values'      => (object) $custom_fields_values,
            ]
        );

        return $profile_dto;
    }

    public function get_current_user_full_profile_data(): FullProfileDTO {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            throw new Exception("User not authenticated.", 401);
        }
        return $this->get_full_profile_data(UserId::fromInt($user_id));
    }

    public function get_user_dashboard_data(UserId $userId): array {
        return [
            'lifetime_points' => $this->userRepo->getLifetimePoints($userId),
        ];
    }
    
    public function request_password_reset(string $email): void {
        // <<<--- REFACTOR: Use the wrapper for all checks and actions
        if (!$this->wp->isEmail($email) || !$this->wp->emailExists($email)) {
            return;
        }

        $user = $this->userRepo->getUserCoreDataBy('email', $email);
        $token = $this->wp->getPasswordResetKey($user);

        if ($this->wp->isWpError($token)) {
            error_log('Could not generate password reset token for ' . $email);
            return;
        }
        
        // This logic is okay, as ConfigService uses the wrapper
        $options = $this->container->get(\CannaRewards\Services\ConfigService::class)->get_app_config();
        $base_url = !empty($options['settings']['brand_personality']['frontend_url']) ? rtrim($options['settings']['brand_personality']['frontend_url'], '/') : $this->wp->homeUrl();
        $reset_link = "$base_url/reset-password?token=$token&email=" . rawurlencode($email);

        $this->wp->sendMail($email, 'Your Password Reset Request', "Click to reset: $reset_link");
    }

    public function perform_password_reset(string $token, string $email, string $password): void {
        // <<<--- REFACTOR: Use the wrapper
        $user = $this->wp->checkPasswordResetKey($token, $email);
        if ($this->wp->isWpError($user)) {
             throw new Exception('Your password reset token is invalid or has expired.', 400);
        }
        $this->wp->resetPassword($user, $password);
    }
    
    public function login(string $username, string $password): array {
        // Use WordPress REST API to login
        $request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $request->set_body_params([
            'username' => $username,
            'password' => $password
        ]);
        $response = $this->wp->restDoRequest($request);

        if ($response->is_error()) {
            throw new Exception('Could not generate authentication token after registration.');
        }

        return $response->get_data();
    }
}