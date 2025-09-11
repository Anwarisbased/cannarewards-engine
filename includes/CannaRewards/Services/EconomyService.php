<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\GrantPointsCommand;
use CannaRewards\Includes\EventBusInterface;
use Exception;
use Psr\Container\ContainerInterface;

final class EconomyService {
    private array $command_map = [];
    private array $policy_map = [];
    private ContainerInterface $container;
    private RankService $rankService;
    private ContextBuilderService $contextBuilder;
    private EventBusInterface $eventBus; // <<<--- ADD PROPERTY

    public function __construct(
        ContainerInterface $container,
        array $policy_map,
        RankService $rankService,
        ContextBuilderService $contextBuilder,
        EventBusInterface $eventBus // <<<--- ADD DEPENDENCY
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;
        $this->contextBuilder = $contextBuilder;
        $this->eventBus = $eventBus; // <<<--- ASSIGN DEPENDENCY

        // REFACTOR: Use the injected event bus
        $this->eventBus->listen('points_to_be_granted', [$this, 'handle_grant_points_event']);
        $this->eventBus->listen('user_points_granted', [$this, 'handleRankTransitionCheck']);
        
        $this->registerCommandHandlers();
    }

    private function registerCommandHandlers(): void {
        $this->command_map = [
            \CannaRewards\Commands\GrantPointsCommand::class => \CannaRewards\Commands\GrantPointsCommandHandler::class,
            \CannaRewards\Commands\RedeemRewardCommand::class => \CannaRewards\Commands\RedeemRewardCommandHandler::class,
            \CannaRewards\Commands\ProcessProductScanCommand::class => \CannaRewards\Commands\ProcessProductScanCommandHandler::class,
            \CannaRewards\Commands\ProcessUnauthenticatedClaimCommand::class => \CannaRewards\Commands\ProcessUnauthenticatedClaimCommandHandler::class,
        ];
    }
    
    public function handle($command) {
        $command_class = get_class($command);

        $policies = $this->policy_map[$command_class] ?? [];
        foreach ($policies as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command);
        }

        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for command: {$command_class}");
        }
        
        $handler = $this->container->get($this->command_map[$command_class]);
        return $handler->handle($command);
    }
    
    public function handle_grant_points_event(array $payload) {
        if (isset($payload['user_id'], $payload['points'], $payload['description'])) {
            $command = new GrantPointsCommand(
                (int) $payload['user_id'],
                (int) $payload['points'],
                (string) $payload['description']
            );
            $this->handle($command);
        }
    }
    
    public function handleRankTransitionCheck(array $payload) {
        $user_id = $payload['user_id'] ?? 0;
        if ($user_id <= 0) return;

        $current_rank_key = $this->container->get(\CannaRewards\Repositories\UserRepository::class)->getCurrentRankKey($user_id);
        $new_rank_dto = $this->rankService->getUserRank($user_id);

        if ($new_rank_dto->key !== $current_rank_key) {
            $this->container->get(\CannaRewards\Repositories\UserRepository::class)->savePointsAndRank(
                $user_id,
                $this->container->get(\CannaRewards\Repositories\UserRepository::class)->getPointsBalance($user_id),
                $this->container->get(\CannaRewards\Repositories\UserRepository::class)->getLifetimePoints($user_id),
                $new_rank_dto->key
            );
            
            $context = $this->contextBuilder->build_event_context($user_id);
            
            // REFACTOR: Use the injected event bus
            $this->eventBus->broadcast('user_rank_changed', $context);
        }
    }
}