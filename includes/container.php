<?php
use CannaRewards\Commands;
use CannaRewards\Policies;
use CannaRewards\Repositories;
use CannaRewards\Services;
use CannaRewards\CannaRewardsEngine;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Infrastructure\WordPressEventBus;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

use function DI\create;
use function DI\get;
use function DI\autowire;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

$containerBuilder->addDefinitions([
    // --- CONFIGURATION ARRAYS ---
    'economy_policy_map' => [
        Commands\RedeemRewardCommand::class => [ Policies\UserCanAffordRedemptionPolicy::class ],
    ],
    'user_policy_map' => [
        Commands\CreateUserCommand::class => [ Policies\UserAccountIsUniquePolicy::class ],
    ],
    'economy_command_map' => [
        Commands\GrantPointsCommand::class => Commands\GrantPointsCommandHandler::class,
        Commands\RedeemRewardCommand::class => Commands\RedeemRewardCommandHandler::class,
        Commands\ProcessProductScanCommand::class => Commands\ProcessProductScanCommandHandler::class,
        Commands\ProcessUnauthenticatedClaimCommand::class => Commands\ProcessUnauthenticatedClaimCommandHandler::class,
    ],

    // --- INTERFACE BINDING & SINGLETONS ---
    EventBusInterface::class => autowire(WordPressEventBus::class),
    \CannaRewards\Infrastructure\WordPressApiWrapper::class => autowire(\CannaRewards\Infrastructure\WordPressApiWrapper::class),

    // --- ADMIN CLASSES ---
    \CannaRewards\Admin\FieldFactory::class => create(),
    \CannaRewards\Admin\AdminMenu::class => autowire(),
    \CannaRewards\Admin\ProductMetabox::class => autowire(),
    \CannaRewards\Admin\UserProfile::class => autowire(),
    
    // --- API CLASSES ---
    \CannaRewards\Api\Router::class => autowire(),
    \CannaRewards\Api\Policies\CanViewOwnResourcePolicy::class => create(),

    // --- REPOSITORIES ---
    Repositories\UserRepository::class => create(Repositories\UserRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\ProductRepository::class => create(Repositories\ProductRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\RewardCodeRepository::class => create(Repositories\RewardCodeRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\ActionLogRepository::class => create(Repositories\ActionLogRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\CustomFieldRepository::class => create(Repositories\CustomFieldRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\OrderRepository::class => create(Repositories\OrderRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\AchievementRepository::class => create(Repositories\AchievementRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),
        
    Repositories\SettingsRepository::class => create(Repositories\SettingsRepository::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),

    // --- EXPLICIT WIRING FOR SERVICES ---
    Services\ContentService::class => create(Services\ContentService::class)
        ->constructor(get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)),

    // ... you might want a section for controllers ...
    \CannaRewards\Api\PageController::class => create(\CannaRewards\Api\PageController::class)
        ->constructor(get(Services\ContentService::class)),
    
    \CannaRewards\Api\AuthController::class => create(\CannaRewards\Api\AuthController::class)
        ->constructor(get(Services\UserService::class)),

    Services\EconomyService::class => create(Services\EconomyService::class)
        ->constructor(
            get(ContainerInterface::class),
            get('economy_policy_map'),
            get('economy_command_map'),
            get(Services\RankService::class),
            get(Services\ContextBuilderService::class),
            get(EventBusInterface::class),
            get(Repositories\UserRepository::class),
            get(Commands\GrantPointsCommandHandler::class)
        ),
    
    // FIXED: RankService only needs UserRepository and WordPressApiWrapper
    Services\RankService::class => create(Services\RankService::class)
        ->constructor(
            get(Repositories\UserRepository::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),

    Services\UserService::class => create(Services\UserService::class)
        ->constructor(
            get(ContainerInterface::class),
            get('user_policy_map'),
            get(Services\RankService::class),
            get(Repositories\CustomFieldRepository::class),
            get(Repositories\UserRepository::class),
            get(Repositories\OrderRepository::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Services\ActionLogService::class => create(Services\ActionLogService::class)
        ->constructor(
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Services\ContextBuilderService::class => create(Services\ContextBuilderService::class)
        ->constructor(
            get(Services\RankService::class),
            get(Repositories\ActionLogRepository::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Services\ConfigService::class => create(Services\ConfigService::class)
        ->constructor(
            get(Services\RankService::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class),
            get(Repositories\SettingsRepository::class)
        ),
        
    Services\StandardScanService::class => create(Services\StandardScanService::class)
        ->constructor(
            get(Repositories\ProductRepository::class),
            get(Commands\GrantPointsCommandHandler::class),
            get(EventBusInterface::class)
        ),
        
    Services\FirstScanBonusService::class => create(Services\FirstScanBonusService::class)
        ->constructor(
            get(Services\ConfigService::class),
            get(Commands\RedeemRewardCommandHandler::class),
            get(EventBusInterface::class)
        ),
        
    Services\GamificationService::class => create(Services\GamificationService::class)
        ->constructor(
            get(Services\EconomyService::class),
            get(Services\ActionLogService::class),
            get(Repositories\AchievementRepository::class),
            get(Repositories\ActionLogRepository::class),
            get(Services\RulesEngineService::class),
            get(EventBusInterface::class)
        ),
        
    Services\ReferralService::class => create(Services\ReferralService::class)
        ->constructor(
            get(Services\CDPService::class),
            get(Repositories\UserRepository::class),
            get(Repositories\ActionLogRepository::class),
            get(EventBusInterface::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class) // <<<--- ADD DEPENDENCY
        ),
        
    Services\CatalogService::class => autowire(Services\CatalogService::class),
        
    Services\RulesEngineService::class => create(Services\RulesEngineService::class)
        ->constructor(
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Services\CDPService::class => create(Services\CDPService::class)
        ->constructor(
            get(Services\RankService::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    // --- POLICIES ---
    Policies\UserCanAffordRedemptionPolicy::class => create(Policies\UserCanAffordRedemptionPolicy::class)
        ->constructor(
            get(Repositories\ProductRepository::class),
            get(Repositories\UserRepository::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    // --- COMMAND HANDLERS ---
    Commands\RegisterWithTokenCommandHandler::class => create(Commands\RegisterWithTokenCommandHandler::class)
        ->constructor(
            get(Services\UserService::class),
            get(Services\EconomyService::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Commands\ProcessUnauthenticatedClaimCommandHandler::class => create(Commands\ProcessUnauthenticatedClaimCommandHandler::class)
        ->constructor(
            get(Repositories\RewardCodeRepository::class),
            get(Repositories\ProductRepository::class),
            get(Services\ConfigService::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
        
    Commands\ProcessProductScanCommandHandler::class => create(Commands\ProcessProductScanCommandHandler::class)
        ->constructor(
            get(Repositories\RewardCodeRepository::class),
            get(Repositories\ProductRepository::class),
            get(Repositories\ActionLogRepository::class),
            get(Services\ActionLogService::class),
            get(EventBusInterface::class),
            get(Services\ContextBuilderService::class)
        ),
        
    Commands\GrantPointsCommandHandler::class => create(Commands\GrantPointsCommandHandler::class)
        ->constructor(
            get(Repositories\UserRepository::class),
            get(Services\ActionLogService::class),
            get(Services\RankService::class),
            get(EventBusInterface::class)
        ),
        
    Commands\RedeemRewardCommandHandler::class => create(Commands\RedeemRewardCommandHandler::class)
        ->constructor(
            get(Repositories\ProductRepository::class),
            get(Repositories\UserRepository::class),
            get(Repositories\OrderRepository::class),
            get(Services\ActionLogService::class),
            get(Services\ContextBuilderService::class),
            get(Repositories\ActionLogRepository::class),
            get(EventBusInterface::class),
            get(\CannaRewards\Infrastructure\WordPressApiWrapper::class)
        ),
    
    CannaRewardsEngine::class => create(CannaRewardsEngine::class)
        ->constructor(get(ContainerInterface::class)),
        
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

return $containerBuilder->build();