<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ReferralService;
use CannaRewards\Services\CDPService;
use CannaRewards\Includes\EventBusInterface;
use Exception;

final class CreateUserCommandHandler {
    private $user_repository;
    private $cdp_service;
    private $referral_service;
    private EventBusInterface $eventBus;

    public function __construct(
        UserRepository $user_repository,
        CDPService $cdp_service,
        ReferralService $referral_service,
        EventBusInterface $eventBus
    ) {
        $this->user_repository = $user_repository;
        $this->cdp_service = $cdp_service;
        $this->referral_service = $referral_service;
        $this->eventBus = $eventBus;
    }

    public function handle(CreateUserCommand $command): array {
        // This is an acceptable global function call, as it's an application-level guard.
        if (!get_option('users_can_register')) {
            throw new Exception('User registration is currently disabled.', 503);
        }

        if (empty($command->password)) {
            throw new Exception('A password is required.', 400);
        }

        // --- REFACTORED LOGIC ---
        // The direct calls to wp_insert_user and update_user_meta have been removed.
        // The handler now delegates persistence to the UserRepository, cleaning up the logic here.
        $user_id = $this->user_repository->createUser(
            (string) $command->email,
            $command->password,
            $command->first_name,
            $command->last_name
        );

        $this->user_repository->saveInitialMeta($user_id, $command->phone, $command->agreed_to_marketing);
        $this->user_repository->savePointsAndRank($user_id, 0, 0, 'member');
        // --- END REFACTORED LOGIC ---

        // The remaining business logic is unchanged.
        $this->referral_service->generate_code_for_new_user($user_id, $command->first_name);

        if ($command->referral_code) {
            $this->referral_service->process_new_user_referral($user_id, $command->referral_code);
        }
        
        $this->eventBus->broadcast('user_created', ['user_id' => $user_id, 'referral_code' => $command->referral_code]);
        $this->cdp_service->track($user_id, 'user_created', ['signup_method' => 'password', 'referral_code_used' => $command->referral_code]);

        return ['success' => true, 'message' => 'Registration successful.', 'userId' => $user_id];
    }
}