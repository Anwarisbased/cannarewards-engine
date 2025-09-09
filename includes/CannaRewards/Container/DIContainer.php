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
        // --- PHASE 1: REPOSITORIES (No dependencies) ---
        $this->registry[Repositories\UserRepository::class] = new Repositories\UserRepository();
        $this->registry[Repositories\ActionLogRepository::class] = new Repositories\ActionLogRepository();
        $this->registry[Repositories\AchievementRepository::class] = new Repositories\AchievementRepository();
        $this->registry[Repositories\OrderRepository::class] = new Repositories\OrderRepository();
        $this->registry[Repositories\ProductRepository::class] = new Repositories\ProductRepository();
        $this->registry[Repositories\RewardCodeRepository::class] = new Repositories\RewardCodeRepository();
        
        // --- PHASE 2: POLICIES (Depend on Repositories) ---
        $this->registry[Policies\UserCanAffordRedemptionPolicy::class] = new Policies\UserCanAffordRedemptionPolicy(
            $this->get(Repositories\ProductRepository::class),
            $this->get(Repositories\UserRepository::class)
        );
        $this->registry[Policies\UserAccountIsUniquePolicy::class] = new Policies\UserAccountIsUniquePolicy();

        // --- PHASE 3: FOUNDATIONAL SERVICES (Depend on Repositories or nothing) ---
        $this->registry[Services\RankService::class] = new Services\RankService($this->get(Repositories\UserRepository::class));
        $this->registry[Services\RuleConditionRegistryService::class] = new Services\RuleConditionRegistryService($this->get(Services\RankService::class));
        $this->registry[Services\RulesEngineService::class] = new Services\RulesEngineService();
        $this->registry[Services\ActionLogService::class] = new Services\ActionLogService();
        $this->registry[Services\ContentService::class] = new Services\ContentService();
        $this->registry[Services\ContextBuilderService::class] = new Services\ContextBuilderService($this->get(Services\RankService::class));
        $this->registry[Services\EventFactory::class] = new Services\EventFactory($this->get(Services\ContextBuilderService::class));
        $this->registry[Services\CDPService::class] = new Services\CDPService($this->get(Services\RankService::class));
        $this->registry[Services\ConfigService::class] = new Services\ConfigService($this->get(Services\RankService::class));
        
        // --- PHASE 4: COMPLEX SERVICES (Depend on other Services) ---
        $user_policy_map = [ Commands\CreateUserCommand::class => [ Policies\UserAccountIsUniquePolicy::class, ], ];
        $user_service = new Services\UserService(
            $this->get(Services\CDPService::class),
            $this->get(Services\ActionLogService::class),
            $this,
            $user_policy_map,
            $this->get(Services\RankService::class)
        );
        $this->registry[Services\UserService::class] = $user_service;

        $economy_policy_map = [ Commands\RedeemRewardCommand::class => [ Policies\UserCanAffordRedemptionPolicy::class, ], ];
        $economy_service = new Services\EconomyService(
            $this->get(Services\ActionLogService::class),
            $this->get(Services\ContextBuilderService::class),
            $this->get(Repositories\RewardCodeRepository::class),
            $this->get(Repositories\ProductRepository::class),
            $this,
            $economy_policy_map,
            $this->get(Services\RankService::class)
        );
        $this->registry[Services\EconomyService::class] = $economy_service;

        $referral_service = new Services\ReferralService($economy_service, $this->get(Services\CDPService::class), $this->get(Repositories\UserRepository::class), $this->get(Repositories\ActionLogRepository::class));
        $this->registry[Services\ReferralService::class] = $referral_service;
        
        $gamification_service = new Services\GamificationService(
            $economy_service,
            $this->get(Services\ActionLogService::class),
            $this->get(Repositories\AchievementRepository::class),
            $this->get(Repositories\ActionLogRepository::class),
            $this->get(Services\RulesEngineService::class)
        );
        $this->registry[Services\GamificationService::class] = $gamification_service;

        // --- PHASE 5: RESOLVE CIRCULAR DEPENDENCIES ---
        $economy_service->set_user_service($user_service);
        $user_service->set_referral_service($referral_service);

        // --- PHASE 6: COMMAND HANDLERS (Depend on Services) ---
        $create_user_handler = new Commands\CreateUserCommandHandler($this->get(Repositories\UserRepository::class), $this->get(Services\CDPService::class));
        $create_user_handler->setReferralService($referral_service);
        $user_service->registerCommandHandler(Commands\CreateUserCommand::class, $create_user_handler);
        
        $update_user_handler = new Commands\UpdateProfileCommandHandler($this->get(Services\ActionLogService::class), $this->get(Services\CDPService::class), $this->get(Repositories\UserRepository::class));
        $user_service->registerCommandHandler(Commands\UpdateProfileCommand::class, $update_user_handler);
        
        $redeem_handler = new Commands\RedeemRewardCommandHandler($this->get(Repositories\ProductRepository::class), $this->get(Repositories\UserRepository::class), $this->get(Repositories\OrderRepository::class), $this->get(Services\ActionLogService::class), $this->get(Services\ContextBuilderService::class), $this->get(Repositories\ActionLogRepository::class));
        $economy_service->registerCommandHandler(Commands\RedeemRewardCommand::class, $redeem_handler);
        $this->registry[Commands\RedeemRewardCommandHandler::class] = $redeem_handler; 
        
        $process_scan_handler = new Commands\ProcessProductScanCommandHandler(
            $this->get(Repositories\RewardCodeRepository::class), 
            $this->get(Repositories\ProductRepository::class), 
            $economy_service, 
            $this->get(Services\ContextBuilderService::class), 
            $this->get(Services\ActionLogService::class),
            $this->get(Repositories\ActionLogRepository::class),
            $this->get(Commands\RedeemRewardCommandHandler::class),
            $this->get(Services\EventFactory::class)
        );
        $economy_service->registerCommandHandler(Commands\ProcessProductScanCommand::class, $process_scan_handler);
        
        $process_unauth_claim_handler = new Commands\ProcessUnauthenticatedClaimCommandHandler($this->get(Repositories\RewardCodeRepository::class), $this->get(Repositories\ProductRepository::class));
        $economy_service->registerCommandHandler(Commands\ProcessUnauthenticatedClaimCommand::class, $process_unauth_claim_handler);
        
        $register_with_token_handler = new Commands\RegisterWithTokenCommandHandler($user_service, $economy_service);
        $user_service->registerCommandHandler(Commands\RegisterWithTokenCommand::class, $register_with_token_handler);

        // --- PHASE 7: CONTROLLERS (Depend on Services) ---
        $this->registry[Api\AuthController::class] = new Api\AuthController($this->get(Services\UserService::class));
        $this->registry[Api\CatalogController::class] = new Api\CatalogController();
        $this->registry[Api\ClaimController::class] = new Api\ClaimController($this->get(Services\EconomyService::class));
        $this->registry[Api\HistoryController::class] = new Api\HistoryController($this->get(Services\ActionLogService::class));
        $this->registry[Api\OrdersController::class] = new Api\OrdersController($this->get(Repositories\OrderRepository::class));
        $this->registry[Api\PageController::class] = new Api\PageController($this->get(Services\ContentService::class));
        $this->registry[Api\ProfileController::class] = new Api\ProfileController($this->get(Services\UserService::class));
        $this->registry[Api\RedeemController::class] = new Api\RedeemController($this->get(Services\EconomyService::class));
        $this->registry[Api\ReferralController::class] = new Api\ReferralController($this->get(Services\ReferralService::class));
        $this->registry[Api\RulesController::class] = new Api\RulesController($this->get(Services\RuleConditionRegistryService::class));
        $this->registry[Api\UnauthenticatedDataController::class] = new Api\UnauthenticatedDataController();
    }
}