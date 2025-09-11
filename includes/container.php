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

    // --- INTERFACE BINDING & SINGLETONS ---
    //
    // --- THIS IS THE FIX ---
    // The scope() method was removed. The correct way to define a singleton is
    // to create the instance once and have the container return that same instance.
    // The easiest way is to just let the container manage it, and by default,
    // it will be treated as a singleton for the interface binding.
    EventBusInterface::class => autowire(WordPressEventBus::class),
    // --- END FIX ---

    // --- EXPLICIT WIRING FOR SERVICES THAT NEED CONFIG ARRAYS ---
    Services\EconomyService::class => autowire()
        ->constructorParameter('policy_map', get('economy_policy_map')),

    Services\UserService::class => autowire()
        ->constructorParameter('policy_map', get('user_policy_map')),
    
    // The main engine class is also built by the container.
    CannaRewardsEngine::class => create(CannaRewardsEngine::class)
        ->constructor(get(ContainerInterface::class)),
        
    // Allow the container to inject itself if needed.
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

return $containerBuilder->build();