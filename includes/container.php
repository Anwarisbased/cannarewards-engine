<?php
use CannaRewards\Commands;
use CannaRewards\Policies;
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
$containerBuilder->useAutowiring(true); // Enable autowiring

$containerBuilder->addDefinitions([
    // --- CONFIGURATION ARRAYS ---
    'economy_policy_map' => [
        Commands\RedeemRewardCommand::class => [ Policies\UserCanAffordRedemptionPolicy::class ],
    ],
    'user_policy_map' => [
        Commands\CreateUserCommand::class => [ Policies\UserAccountIsUniquePolicy::class ],
    ],
    // NEW: Explicitly define the command-to-handler mapping for the economy service
    'economy_command_map' => [
        Commands\GrantPointsCommand::class => Commands\GrantPointsCommandHandler::class,
        Commands\RedeemRewardCommand::class => Commands\RedeemRewardCommandHandler::class,
        Commands\ProcessProductScanCommand::class => Commands\ProcessProductScanCommandHandler::class,
        Commands\ProcessUnauthenticatedClaimCommand::class => Commands\ProcessUnauthenticatedClaimCommandHandler::class,
    ],

    // --- INTERFACE BINDING & SINGLETONS ---
    EventBusInterface::class => autowire(WordPressEventBus::class),

    // --- EXPLICIT WIRING FOR SERVICES THAT NEED CONFIG ARRAYS ---
    Services\EconomyService::class => autowire()
        ->constructorParameter('policy_map', get('economy_policy_map'))
        ->constructorParameter('command_map', get('economy_command_map')), // Inject the new map

    Services\UserService::class => autowire()
        ->constructorParameter('policy_map', get('user_policy_map')),
    
    // The main engine class is also built by the container.
    CannaRewardsEngine::class => create(CannaRewardsEngine::class)
        ->constructor(get(ContainerInterface::class)),
        
    // Allow the container to inject itself if needed.
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

return $containerBuilder->build();