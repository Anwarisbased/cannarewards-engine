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
    
    // Explicitly define how to build the Command Buses with their policy maps
    Services\UserService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('user_policy_map')),
    
    Services\EconomyService::class => DI\autowire()
        ->constructorParameter('policy_map', DI\get('economy_policy_map')),

    // Allow the container to inject itself
    ContainerInterface::class => function (ContainerInterface $c) {
        return $c;
    },
]);

// STAGE 1: Build the container with all the service definitions.
$container = $containerBuilder->build();

// --- THIS IS THE FIX ---
// STAGE 2: Get the services and manually configure them.
// This is the correct way to handle circular dependencies and handler registration.
// We are NO LONGER trying to get the handlers from the container itself.
$userService = $container->get(Services\UserService::class);
$economyService = $container->get(Services\EconomyService::class);
$referralService = $container->get(Services\ReferralService::class);

// Resolve circular dependencies
$userService->set_referral_service($referralService);
$economyService->set_user_service($userService);

// Manually build and register the handlers. The container will autowire their dependencies.
$userService->registerCommandHandler(
    Commands\CreateUserCommand::class, 
    $container->get(Commands\CreateUserCommandHandler::class)
);
$userService->registerCommandHandler(
    Commands\UpdateProfileCommand::class, 
    $container->get(Commands\UpdateProfileCommandHandler::class)
);
$userService->registerCommandHandler(
    Commands\RegisterWithTokenCommand::class, 
    $container->get(Commands\RegisterWithTokenCommandHandler::class)
);

$economyService->registerCommandHandler(
    Commands\GrantPointsCommand::class, 
    $container->get(Commands\GrantPointsCommandHandler::class)
);
$economyService->registerCommandHandler(
    Commands\RedeemRewardCommand::class, 
    $container->get(Commands\RedeemRewardCommandHandler::class)
);
$economyService->registerCommandHandler(
    Commands\ProcessProductScanCommand::class, 
    $container->get(Commands\ProcessProductScanCommandHandler::class)
);
$economyService->registerCommandHandler(
    Commands\ProcessUnauthenticatedClaimCommand::class, 
    $container->get(Commands\ProcessUnauthenticatedClaimCommandHandler::class)
);

// Manually resolve the final circular dependency for the GrantPointsCommandHandler
$container->get(Commands\GrantPointsCommandHandler::class)->setEconomyService($economyService);

// Return the fully built and configured container.
return $container;