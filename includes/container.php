<?php
// FILE: includes/container.php

use CannaRewards\Commands;
use CannaRewards\Policies;
use CannaRewards\Services;
use CannaRewards\CannaRewardsEngine;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

$containerBuilder->addDefinitions([
    // This is where you will map your Domain Interfaces to your
    // Infrastructure Adapters once we build them in Phase 3.
    // \CannaRewards\Domain\Repositories\UserRepositoryInterface::class => DI\create(\CannaRewards\Infrastructure\Persistence\WordPressUserRepository::class),

    // --- CONFIGURATION ARRAYS ---
    'economy_policy_map' => [
        Commands\RedeemRewardCommand::class => [ Policies\UserCanAffordRedemptionPolicy::class ],
    ],
    'user_policy_map' => [
        Commands\CreateUserCommand::class => [ Policies\UserAccountIsUniquePolicy::class ],
    ],

    // --- EXPLICIT WIRING FOR SERVICES THAT NEED CONFIG ARRAYS ---
    // Here, we tell the container exactly how to build these specific services.
    Services\EconomyService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('economy_policy_map')), // For the param named 'policy_map', use the entry named 'economy_policy_map'

    Services\UserService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('user_policy_map')), // For the param named 'policy_map', use the entry named 'user_policy_map'
    
    // The main engine class is also now built by the container.
    CannaRewardsEngine::class => DI\create(CannaRewardsEngine::class)
        ->constructor(DI\get(ContainerInterface::class)),

    // Allow the container to inject itself if a service truly needs it.
    ContainerInterface::class => fn(ContainerInterface $c) => $c,
]);

// The container now has all the information it needs.
return $containerBuilder->build();