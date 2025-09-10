<?php
use CannaRewards\Commands;
use CannaRewards\Policies;
use CannaRewards\Services;
use CannaRewards\CannaRewardsEngine;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

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

    // --- EXPLICIT WIRING FOR SERVICES THAT NEED CONFIG ARRAYS ---
    Services\EconomyService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('economy_policy_map')),

    Services\UserService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('user_policy_map')),
    
    // The main engine class is also built by the container.
    CannaRewardsEngine::class => DI\create(CannaRewardsEngine::class)
        ->constructor(DI\get(ContainerInterface::class)),
        
    // Allow the container to inject itself if needed.
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

return $containerBuilder->build();