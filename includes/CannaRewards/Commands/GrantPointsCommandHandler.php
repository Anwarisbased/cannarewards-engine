<?php
// FILE: includes/CannaRewards/Commands/GrantPointsCommandHandler.php

namespace CannaRewards\Commands;

use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\RankService;
use CannaRewards\Includes\Event; // <-- IMPORT THE EVENT CLASS

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles the logic for the GrantPointsCommand.
 */
final class GrantPointsCommandHandler {
    private UserRepository $userRepository;
    private ActionLogService $actionLogService;
    private RankService $rankService;

    public function __construct(
        UserRepository $userRepository,
        ActionLogService $actionLogService,
        RankService $rankService
        // --- CHANGE: No longer depends on EconomyService ---
    ) {
        $this->userRepository = $userRepository;
        $this->actionLogService = $actionLogService;
        $this->rankService = $rankService;
    }

    // --- CHANGE: The setEconomyService() method is completely removed ---

    /**
     * Executes the point-granting logic.
     */
    public function handle(GrantPointsCommand $command): array {
        $user_rank_dto    = $this->rankService->getUserRank($command->user_id);
        $rank_multiplier  = $this->getRankMultiplier($user_rank_dto->key);
        $final_multiplier = max( $rank_multiplier, $command->temp_multiplier );
        $points_to_grant  = floor( $command->base_points * $final_multiplier );

        $current_balance     = $this->userRepository->getPointsBalance($command->user_id);
        $new_balance         = $current_balance + $points_to_grant;
        
        $lifetime_points     = $this->userRepository->getLifetimePoints($command->user_id);
        $new_lifetime_points = $lifetime_points + $points_to_grant;

        // Persist the new data
        $this->userRepository->savePointsAndRank($command->user_id, $new_balance, $new_lifetime_points, $user_rank_dto->key);

        $log_meta_data = [
            'description'        => $command->description,
            'points_change'      => $points_to_grant,
            'new_balance'        => $new_balance,
            'base_points'        => $command->base_points,
            'multiplier_applied' => $final_multiplier > 1.0 ? $final_multiplier : null,
        ];
        $this->actionLogService->record( $command->user_id, 'points_granted', 0, $log_meta_data );

        // --- CHANGE: Instead of calling the service, broadcast an event ---
        // This decouples the handler from the service, breaking the circular dependency.
        Event::broadcast('user_points_granted', [
            'user_id' => $command->user_id
        ]);
        // --- END CHANGE ---

        return [
            'points_earned'      => $points_to_grant,
            'new_points_balance' => $new_balance,
        ];
    }

    /**
     * Temporary helper to get a multiplier from a rank key.
     */
    private function getRankMultiplier(string $rankKey): float {
        $rank_post = get_page_by_path($rankKey, OBJECT, 'canna_rank');
        if ($rank_post) {
            $multiplier = get_post_meta($rank_post->ID, 'point_multiplier', true);
            return !empty($multiplier) ? (float) $multiplier : 1.0;
        }
        return 1.0;
    }
}