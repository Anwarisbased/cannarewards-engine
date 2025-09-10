<?php
// FILE: includes/CannaRewards/Services/EconomyService.php

namespace CannaRewards\Services;

use CannaRewards\Includes\Event;
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
    private ContextBuilderService $context_builder;
    private array $command_map = [];
    private array $policy_map = [];
    private ContainerInterface $container;
    private RankService $rankService;

    public function __construct(
        ContextBuilderService $context_builder,
        ContainerInterface $container,
        array $policy_map,
        RankService $rankService
    ) {
        $this->context_builder    = $context_builder;
        $this->container = $container;
        $this->policy_map = $policy_map;
        $this->rankService = $rankService;

        Event::listen('points_to_be_granted', [$this, 'handle_grant_points_event']);
        Event::listen('user_points_granted', [$this, 'handleRankTransitionCheck']);
        
        $this->registerCommandHandlers();
    }

    /**
     * Handles the event to grant points by dispatching the secure command.
     * THIS MUST BE PUBLIC TO BE AN EVENT LISTENER.
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

        $policies_for_command = $this->policy_map[$command_class] ?? [];
        foreach ($policies_for_command as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command);
        }

        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for command: {$command_class}");
        }
        
        $handler_class = $this->command_map[$command_class];
        $handler = $this->container->get($handler_class);

        return $handler->handle($command);
    }
    
    /**
     * Checks for rank transitions. This is an event handler.
     * THIS MUST BE PUBLIC TO BE AN EVENT LISTENER.
     */
    public function handleRankTransitionCheck(array $payload) {
        $user_id = $payload['user_id'] ?? 0;
        if ($user_id <= 0) {
            return;
        }

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