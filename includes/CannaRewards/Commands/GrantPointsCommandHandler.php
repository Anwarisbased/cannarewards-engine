<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\RankService;

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
    private EconomyService $economyService;

    public function __construct(
        UserRepository $userRepository,
        ActionLogService $actionLogService,
        RankService $rankService
    ) {
        $this->userRepository = $userRepository;
        $this->actionLogService = $actionLogService;
        $this->rankService = $rankService;
    }

    /**
     * This setter is used by the DI container to resolve a circular dependency.
     * The handler needs the EconomyService to check for rank transitions,
     * and the EconomyService needs this handler.
     */
    public function setEconomyService(EconomyService $economyService): void {
        $this->economyService = $economyService;
    }

    /**
     * Executes the point-granting logic.
     * This logic is identical to the old EconomyService::grant_points method.
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

        // After granting points, check if the user's rank has changed.
        $this->economyService->check_and_apply_rank_transition($command->user_id);

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