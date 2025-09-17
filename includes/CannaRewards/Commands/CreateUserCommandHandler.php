<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ReferralService;
use CannaRewards\Services\CDPService;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Services\ConfigService; // <<<--- IMPORT
use Exception;

final class CreateUserCommandHandler {
    private $user_repository;
    private $cdp_service;
    private $referral_service;
    private EventBusInterface $eventBus;
    private ConfigService $configService; // <<<--- ADD PROPERTY

    public function __construct(
        UserRepository $user_repository,
        CDPService $cdp_service,
        ReferralService $referral_service,
        EventBusInterface $eventBus,
        ConfigService $configService // <<<--- INJECT
    ) {
        $this->user_repository = $user_repository;
        $this->cdp_service = $cdp_service;
        $this->referral_service = $referral_service;
        $this->eventBus = $eventBus;
        $this->configService = $configService; // <<<--- ASSIGN
    }

    public function handle(CreateUserCommand $command): array {
        // <<<--- REFACTOR: Use the service
        if (!$this->configService->canUsersRegister()) {
            throw new Exception('User registration is currently disabled.', 503);
        }

        if (empty($command->password)) {
            throw new Exception('A password is required.', 400);
        }

        // --- REFACTORED LOGIC ---
        // The direct calls to wp_insert_user and update_user_meta have been removed.
        // The handler now delegates persistence to the UserRepository, cleaning up the logic here.
        $user_id = $this->user_repository->createUser(
            $command->email,
            $command->password,
            $command->firstName,
            $command->lastName
        );

        $user_id_vo = new \CannaRewards\Domain\ValueObjects\UserId($user_id);
        $this->user_repository->saveInitialMeta($user_id_vo, $command->phone ? (string) $command->phone : '', $command->agreedToMarketing);
        $this->user_repository->savePointsAndRank($user_id_vo, 0, 0, 'member');
        // --- END REFACTORED LOGIC ---

        // The remaining business logic is unchanged.
        $this->referral_service->generate_code_for_new_user($user_id, $command->firstName);

        if ($command->referralCode) {
            $this->referral_service->process_new_user_referral($user_id, (string) $command->referralCode);
        }
        
        $this->eventBus->broadcast('user_created', ['user_id' => $user_id, 'referral_code' => $command->referralCode]);
        $this->cdp_service->track($user_id, 'user_created', ['signup_method' => 'password', 'referral_code_used' => $command->referralCode]);

        return ['success' => true, 'message' => 'Registration successful.', 'userId' => $user_id];
    }
}