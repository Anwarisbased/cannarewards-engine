<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\GrantPointsCommand;
use CannaRewards\Commands\GrantPointsCommandHandler;
use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Policies\AuthorizationPolicyInterface;
use CannaRewards\Policies\ValidationPolicyInterface;
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
    private GrantPointsCommandHandler $grantPointsHandler;

    public function __construct(
        ContainerInterface $container,
        array $policy_map,
        array $command_map, // Inject the command map
        RankService $rankService,
        ContextBuilderService $contextBuilder,
        EventBusInterface $eventBus,
        UserRepository $userRepository,
        GrantPointsCommandHandler $grantPointsHandler
    ) {
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->command_map = $command_map; // Assign the injected map
        $this->rankService = $rankService;
        $this->contextBuilder = $contextBuilder;
        $this->eventBus = $eventBus;
        $this->userRepository = $userRepository;
        $this->grantPointsHandler = $grantPointsHandler;

        // Register internal event listeners.
        $this->eventBus->listen('points_to_be_granted', [$this, 'handle_grant_points_event']);
        $this->eventBus->listen('user_points_granted', [$this, 'handleRankTransitionCheck']);
    }

    // This map now declaratively defines all business rules for a command.
    private function getPolicyMap(): array {
        return [
            \CannaRewards\Commands\ProcessProductScanCommand::class => [
                'validation' => [
                    // PolicyClass => function that extracts the VO from the command
                    \CannaRewards\Policies\RewardCodeMustBeValidPolicy::class => fn($cmd) => $cmd->code,
                ],
                'authorization' => []
            ],
            \CannaRewards\Commands\ProcessUnauthenticatedClaimCommand::class => [
                'validation' => [
                    // PolicyClass => function that extracts the VO from the command
                    \CannaRewards\Policies\UnauthenticatedCodeIsValidPolicy::class => fn($cmd) => $cmd->code,
                ],
                'authorization' => []
            ],
            \CannaRewards\Commands\RedeemRewardCommand::class => [
                'validation' => [],
                'authorization' => [
                    \CannaRewards\Policies\UserMustBeAbleToAffordRedemptionPolicy::class,
                    \CannaRewards\Policies\UserMustMeetRankRequirementPolicy::class,
                ]
            ],
        ];
    }
    
    public function handle($command) {
        $commandClass = get_class($command);
        $policyMap = $this->getPolicyMap()[$commandClass] ?? [];

        try {
            // --- Run Validation Policies ---
            foreach ($policyMap['validation'] ?? [] as $policyClass => $valueExtractor) {
                /** @var ValidationPolicyInterface $policy */
                $policy = $this->container->get($policyClass);
                $valueToValidate = $valueExtractor($command);
                error_log("Running policy: " . $policyClass);
                $policy->check($valueToValidate);
            }

            // --- Run Authorization Policies ---
            $userId = $command->userId; // Assuming userId is on the command
            foreach ($policyMap['authorization'] ?? [] as $policyClass) {
                /** @var AuthorizationPolicyInterface $policy */
                $policy = $this->container->get($policyClass);
                $policy->check($userId, $command);
            }
        } catch (\Exception $e) {
            error_log("Exception in EconomyService: " . $e->getMessage() . " Code: " . $e->getCode());
            throw $e;
        }

        // The service now uses the injected map to find the correct handler.
        // It no longer has internal knowledge of which handlers exist.
        if (!isset($this->command_map[$commandClass])) {
            throw new Exception("No economy handler registered for command: {$commandClass}");
        }
        
        $handler_class = $this->command_map[$commandClass];
        $handler = $this->container->get($handler_class); // Use container to build the handler
        return $handler->handle($command);
    }
    
    public function handle_grant_points_event(array $payload) {
        if (isset($payload['user_id'], $payload['points'], $payload['description'])) {
            $command = new GrantPointsCommand(
                UserId::fromInt((int) $payload['user_id']),
                \CannaRewards\Domain\ValueObjects\Points::fromInt((int) $payload['points']),
                (string) $payload['description']
            );
            // REFACTOR: Directly call the handler for a cleaner data flow.
            $this->grantPointsHandler->handle($command);
        }
    }
    
    public function handleRankTransitionCheck(array $payload) {
        $user_id = $payload['user_id'] ?? 0;
        if ($user_id <= 0) return;

        $userIdVO = UserId::fromInt($user_id);
        $current_rank_key = $this->userRepository->getCurrentRankKey($userIdVO);
        $new_rank_dto = $this->rankService->getUserRank($userIdVO);

        error_log("Rank transition check: user_id=$user_id, current_rank=$current_rank_key, new_rank=" . (string)$new_rank_dto->key . ", points_required=" . $new_rank_dto->pointsRequired->toInt());

        if ((string)$new_rank_dto->key !== $current_rank_key) {
            error_log("Rank transition: Updating user $user_id from $current_rank_key to " . (string)$new_rank_dto->key);
            $this->userRepository->savePointsAndRank(
                $userIdVO,
                $this->userRepository->getPointsBalance($userIdVO),
                $this->userRepository->getLifetimePoints($userIdVO),
                (string)$new_rank_dto->key
            );
            
            $context = $this->contextBuilder->build_event_context($user_id);
            
            $this->eventBus->broadcast('user_rank_changed', $context);
        }
    }
}