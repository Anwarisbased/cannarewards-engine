<?php

use CannaRewards\Commands;
use CannaRewards\Policies;
use CannaRewards\Services;
use Psr\Container\ContainerInterface;
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

$containerBuilder->addDefinitions([
    'economy_policy_map' => [
        Commands\RedeemRewardCommand::class => [ Policies\UserCanAffordRedemptionPolicy::class, ],
    ],
    'user_policy_map' => [
        Commands\CreateUserCommand::class => [ Policies\UserAccountIsUniquePolicy::class, ],
    ],

    // Define how to build services with special parameters (like the policy maps)
    Services\EconomyService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('economy_policy_map')),
    
    Services\UserService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('user_policy_map')),

    ContainerInterface::class => function (ContainerInterface $c) {
        return $c;
    },
]);

// --- THIS IS THE FIX: TWO-STAGE BOOTSTRAP ---

// STAGE 1: Build the container with all the services.
$container = $containerBuilder->build();

// STAGE 2: Now that all services EXIST, configure them by registering their handlers.
// This breaks the circular dependency because the services are already constructed.

// Configure UserService
$userService = $container->get(Services\UserService::class);
$userService->registerCommandHandler(Commands\CreateUserCommand::class, $container->get(Commands\CreateUserCommandHandler::class));
$userService->registerCommandHandler(Commands\UpdateProfileCommand::class, $container->get(Commands\UpdateProfileCommandHandler::class));
$userService->registerCommandHandler(Commands\RegisterWithTokenCommand::class, $container->get(Commands\RegisterWithTokenCommandHandler::class));

// Configure EconomyService
$economyService = $container->get(Services\EconomyService::class);
$economyService->registerCommandHandler(Commands\RedeemRewardCommand::class, $container->get(Commands\RedeemRewardCommandHandler::class));
$economyService->registerCommandHandler(Commands\ProcessProductScanCommand::class, $container->get(Commands\ProcessProductScanCommandHandler::class));
$economyService->registerCommandHandler(Commands\ProcessUnauthenticatedClaimCommand::class, $container->get(Commands\ProcessUnauthenticatedClaimCommandHandler::class));

// Resolve the final circular dependencies using setters
$referralService = $container->get(Services\ReferralService::class);
$userService->set_referral_service($referralService);
$economyService->set_user_service($userService);


// Return the fully built and configured container.
return $container;