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
$containerBuilder->useAutowiring(true); // Autowiring is still great for simpler classes

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

    // --- EXPLICIT WIRING FOR SERVICES ---
    // This is the core fix. We are now explicitly defining every constructor parameter,
    // ensuring the container knows exactly how to build these complex services.
    Services\EconomyService::class => create(Services\EconomyService::class)
        ->constructor(
            get(ContainerInterface::class),
            get('economy_policy_map'),
            get('economy_command_map'),
            get(Services\RankService::class),
            get(Services\ContextBuilderService::class),
            get(EventBusInterface::class),
            get(Repositories\UserRepository::class)
        ),

    Services\UserService::class => create(Services\UserService::class)
        ->constructor(
            get(ContainerInterface::class),
            get('user_policy_map'),
            get(Services\RankService::class),
            get(Repositories\CustomFieldRepository::class),
            get(Repositories\UserRepository::class)
        ),
    
    // The main engine class is also built by the container.
    CannaRewardsEngine::class => create(CannaRewardsEngine::class)
        ->constructor(get(ContainerInterface::class)),
        
    // Allow the container to inject itself if needed.
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

return $containerBuilder->build();