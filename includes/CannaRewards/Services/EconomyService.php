<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;
use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use Exception;
use Psr\Container\ContainerInterface;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Economy Service (A Pure Command Bus)
 */
class EconomyService {
    private ActionLogService $action_log_service;
    private ContextBuilderService $context_builder;
    private ?UserService $user_service = null;
    private array $command_map = [];
    private RewardCodeRepository $reward_code_repository;
    private ProductRepository $product_repository;
    private array $policy_map = [];
    private ContainerInterface $container;
    private RankService $rankService;

    public function __construct(
        ActionLogService $action_log_service,
        ContextBuilderService $context_builder,
        RewardCodeRepository $reward_code_repository,
        ProductRepository $product_repository,
        ContainerInterface $container,
        array $policy_map,
        RankService $rankService
    ) {
        $this->action_log_service = $action_log_service;
        $this->context_builder    = $context_builder;
        $this->reward_code_repository = $reward_code_repository;
        $this->product_repository = $product_repository;
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;

        // Listen for the event broadcast by other services like ReferralService.
        Event::listen('points_to_be_granted', [$this, 'handle_grant_points_event']);
    }

    /**
     * Handles the event to grant points by dispatching the secure command.
     */
    public function handle_grant_points_event(array $payload) {
        if (isset($payload['user_id'], $payload['points'], $payload['description'])) {
            $command = new \CannaRewards\Commands\GrantPointsCommand(
                (int) $payload['user_id'],
                (int) $payload['points'],
                (string) $payload['description']
            );
            $this->handle($command);
        }
    }

    public function set_user_service(UserService $user_service) {
        $this->user_service = $user_service;
    }

    public function registerCommandHandler(string $command_class, object $handler_instance): void {
        $this->command_map[$command_class] = $handler_instance;
    }

    public function handle($command) {
        $command_class = get_class($command);

        $policies_for_command = $this->policy_map[$command_class] ?? [];
        foreach ($policies_for_command as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command);
        }

        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for command: {$command_class}");
        }
        $handler = $this->command_map[$command_class];

        if (method_exists($handler, 'setEconomyService')) {
            $handler->setEconomyService($this);
        }

        return $handler->handle($command);
    }

    /**
     * Checks for rank transitions. This method is public so the
     * GrantPointsCommandHandler can call it.
     */
    public function check_and_apply_rank_transition( int $user_id ) {
        $current_rank_key = get_user_meta( $user_id, '_canna_current_rank_key', true ) ?: 'member';
        
        $new_rank_dto = $this->rankService->getUserRank($user_id);
        $new_rank_key = $new_rank_dto->key;

        if ( $new_rank_key !== $current_rank_key ) {
            update_user_meta( $user_id, '_canna_current_rank_key', $new_rank_key );
            
            $all_ranks_dtos = $this->rankService->getRankStructure();
            $all_ranks = array_reduce($all_ranks_dtos, function ($carry, $item) {
                $carry[$item->key] = (array) $item;
                return $carry;
            }, []);
            
            $old_rank_object = $all_ranks[ $current_rank_key ] ?? null;
            $new_rank_object = $all_ranks[ $new_rank_key ] ?? null;
            
            $context = $this->context_builder->build_event_context($user_id);

            Event::broadcast('user_rank_changed', array_merge($context, [
                'old_rank' => $old_rank_object,
                'new_rank' => $new_rank_object,
            ]));
        }
    }
}