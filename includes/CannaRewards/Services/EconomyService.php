<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\GrantPointsCommand;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Repositories\UserRepository;
use Exception;
use Psr\Container\ContainerInterface;

final class EconomyService {
    private array $command_map; // Changed from private property to constructor-injected
    private array $policy_map;
    private ContainerInterface $container;
    private RankService $rankService;
    private ContextBuilderService $contextBuilder;
    private EventBusInterface $eventBus;
    private UserRepository $userRepository;

    public function __construct(
        ContainerInterface $container,
        array $policy_map,
        array $command_map, // Inject the command map
        RankService $rankService,
        ContextBuilderService $contextBuilder,
        EventBusInterface $eventBus,
        UserRepository $userRepository
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->command_map = $command_map; // Assign the injected map
        $this->rankService = $rankService;
        $this->contextBuilder = $contextBuilder;
        $this->eventBus = $eventBus;
        $this->userRepository = $userRepository;

        $this->eventBus->listen('points_to_be_granted', [$this, 'handle_grant_points_event']);
        $this->eventBus->listen('user_points_granted', [$this, 'handleRankTransitionCheck']);
        
        // The private registerCommandHandlers() method is no longer needed.
    }

    public function handle($command) {
        $command_class = get_class($command);

        // --- REFACTORED LOGIC ---
        // This policy logic remains the same, as policies are also configured via DI.
        $policies = $this->policy_map[$command_class] ?? [];
        foreach ($policies as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command);
        }

        // The service now uses the injected map to find the correct handler.
        // It no longer has internal knowledge of which handlers exist.
        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No economy handler registered for command: {$command_class}");
        }
        
        $handler_class = $this->command_map[$command_class];
        $handler = $this->container->get($handler_class); // Use container to build the handler
        return $handler->handle($command);
        // --- END REFACTORED LOGIC ---
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

        $current_rank_key = $this->userRepository->getCurrentRankKey($user_id);
        $new_rank_dto = $this->rankService->getUserRank($user_id);

        if ($new_rank_dto->key !== $current_rank_key) {
            $this->userRepository->savePointsAndRank(
                $user_id,
                $this->userRepository->getPointsBalance($user_id),
                $this->userRepository->getLifetimePoints($user_id),
                $new_rank_dto->key
            );
            
            $context = $this->contextBuilder->build_event_context($user_id);
            
            $this->eventBus->broadcast('user_rank_changed', $context);
        }
    }
}