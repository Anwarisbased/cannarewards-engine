<?php
namespace CannaRewards\Container;

use CannaRewards\Repositories;
use CannaRewards\Services;
use CannaRewards\Commands;
use CannaRewards\Policies;
use CannaRewards\Api;

class DIContainer {
    private $registry = [];

    public function __construct() {
        $this->bootstrap();
    }

    public function get(string $class_name) {
        if (!isset($this->registry[$class_name])) {
            throw new \Exception("Service not found in container: " . $class_name);
        }
        return $this->registry[$class_name];
    }

    private function bootstrap(): void {
        // --- REPOSITORIES ---
        $this->registry[Repositories\ActionLogRepository::class] = new Repositories\ActionLogRepository();
        $this->registry[Repositories\AchievementRepository::class] = new Repositories\AchievementRepository();
        $this->registry[Repositories\OrderRepository::class] = new Repositories\OrderRepository();
        $this->registry[Repositories\ProductRepository::class] = new Repositories\ProductRepository();
        $this->registry[Repositories\RewardCodeRepository::class] = new Repositories\RewardCodeRepository();
        $this->registry[Repositories\UserRepository::class] = new Repositories\UserRepository();
        
        // --- POLICIES ---
        $this->registry[Policies\UserCanAffordRedemptionPolicy::class] = new Policies\UserCanAffordRedemptionPolicy(
            $this->get(Repositories\ProductRepository::class),
            $this->get(Repositories\UserRepository::class)
        );
        $this->registry[Policies\UserAccountIsUniquePolicy::class] = new Policies\UserAccountIsUniquePolicy();

        $economy_policy_map = [
            Commands\RedeemRewardCommand::class => [
                Policies\UserCanAffordRedemptionPolicy::class,
            ],
        ];

        $user_policy_map = [
            Commands\CreateUserCommand::class => [
                Policies\UserAccountIsUniquePolicy::class,
            ],
        ];

        // --- SERVICES ---
        $this->registry[Services\RankService::class] = new Services\RankService($this->get(Repositories\UserRepository::class));
        $this->registry[Services\ActionLogService::class] = new Services\ActionLogService();
        $this->registry[Services\CDPService::class] = new Services\CDPService($this->get(Services\RankService::class));
        $this->registry[Services\ConfigService::class] = new Services\ConfigService($this->get(Services\RankService::class));
        $this->registry[Services\ContentService::class] = new Services\ContentService();
        $this->registry[Services\ContextBuilderService::class] = new Services\ContextBuilderService();
        $this->registry[Services\EventFactory::class] = new Services\EventFactory($this->get(Services\ContextBuilderService::class));
        
        $user_service = new Services\UserService(
            $this->get(Services\CDPService::class),
            $this->get(Services\ActionLogService::class),
            $this,
            $user_policy_map,
            $this->get(Services\RankService::class)
        );
        $this->registry[Services\UserService::class] = $user_service;

        $economy_service = new Services\EconomyService(
            $this->get(Services\ActionLogService::class),
            $this->get(Services\ContextBuilderService::class),
            $this->get(Repositories\RewardCodeRepository::class),
            $this->get(Repositories\ProductRepository::class),
            $this,
            $economy_policy_map,
            $this->get(Services\RankService::class)
        );
        $economy_service->set_user_service($user_service);
        $this->registry[Services\EconomyService::class] = $economy_service;

        $referral_service = new Services\ReferralService($economy_service, $this->get(Services\CDPService::class), $this->get(Repositories\UserRepository::class), $this->get(Repositories\ActionLogRepository::class));
        $user_service->set_referral_service($referral_service);
        $this->registry[Services\ReferralService::class] = $referral_service;

        $this->registry[Services\GamificationService::class] = new Services\GamificationService($economy_service, $this->get(Services\ActionLogService::class), $this->get(Repositories\AchievementRepository::class), $this->get(Repositories\ActionLogRepository::class));
        
        // --- COMMAND HANDLERS ---
        $create_user_handler = new Commands\CreateUserCommandHandler($this->get(Repositories\UserRepository::class), $this->get(Services\CDPService::class));
        $create_user_handler->setReferralService($referral_service);
        $user_service->registerCommandHandler(Commands\CreateUserCommand::class, $create_user_handler);

        $update_user_handler = new Commands\UpdateProfileCommandHandler($this->get(Services\ActionLogService::class), $this->get(Services\CDPService::class), $this->get(Repositories\UserRepository::class));
        $user_service->registerCommandHandler(Commands\UpdateProfileCommand::class, $update_user_handler);
        
        $redeem_handler = new Commands\RedeemRewardCommandHandler($this->get(Repositories\ProductRepository::class), $this->get(Repositories\UserRepository::class), $this->get(Repositories\OrderRepository::class), $this->get(Services\ActionLogService::class), $this->get(Services\ContextBuilderService::class), $this->get(Repositories\ActionLogRepository::class));
        $economy_service->registerCommandHandler(Commands\RedeemRewardCommand::class, $redeem_handler);
        $this->registry[Commands\RedeemRewardCommandHandler::class] = $redeem_handler; 
        
        // --- THE FIX IS HERE ---
        // The arguments are now in the correct order, matching the handler's constructor.
        $process_scan_handler = new Commands\ProcessProductScanCommandHandler(
            $this->get(Repositories\RewardCodeRepository::class), 
            $this->get(Repositories\ProductRepository::class), 
            $economy_service, 
            $this->get(Services\ContextBuilderService::class), // <-- CORRECTED
            $this->get(Services\ActionLogService::class),      // <-- CORRECTED
            $this->get(Repositories\ActionLogRepository::class),
            $this->get(Commands\RedeemRewardCommandHandler::class),
            $this->get(Services\EventFactory::class)
        );
        $economy_service->registerCommandHandler(Commands\ProcessProductScanCommand::class, $process_scan_handler);
        
        $process_unauth_claim_handler = new Commands\ProcessUnauthenticatedClaimCommandHandler($this->get(Repositories\RewardCodeRepository::class), $this->get(Repositories\ProductRepository::class));
        $economy_service->registerCommandHandler(Commands\ProcessUnauthenticatedClaimCommand::class, $process_unauth_claim_handler);
        
        $register_with_token_handler = new Commands\RegisterWithTokenCommandHandler($user_service, $economy_service);
        $user_service->registerCommandHandler(Commands\RegisterWithTokenCommand::class, $register_with_token_handler);

        // --- CONTROLLERS ---
        $this->registry[Api\AuthController::class] = new Api\AuthController($this->get(Services\UserService::class));
        $this->registry[Api\CatalogController::class] = new Api\CatalogController();
        $this->registry[Api\ClaimController::class] = new Api\ClaimController($this->get(Services\EconomyService::class));
        $this->registry[Api\HistoryController::class] = new Api\HistoryController($this->get(Services\ActionLogService::class));
        $this->registry[Api\OrdersController::class] = new Api\OrdersController($this->get(Repositories\OrderRepository::class));
        $this->registry[Api\PageController::class] = new Api\PageController($this->get(Services\ContentService::class));
        $this->registry[Api\ProfileController::class] = new Api\ProfileController($this->get(Services\UserService::class));
        $this->registry[Api\RedeemController::class] = new Api\RedeemController($this->get(Services\EconomyService::class));
        $this->registry[Api\ReferralController::class] = new Api\ReferralController($this->get(Services\ReferralService::class));
        $this->registry[Api\UnauthenticatedDataController::class] = new Api\UnauthenticatedDataController();
    }
}