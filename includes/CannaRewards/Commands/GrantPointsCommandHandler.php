<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\RankService;
use CannaRewards\Includes\Event;

final class GrantPointsCommandHandler {
    private UserRepository $userRepository;
    private ActionLogService $actionLogService;
    private RankService $rankService;

    public function __construct(
        UserRepository $userRepository,
        ActionLogService $actionLogService,
        RankService $rankService
    ) {
        $this->userRepository = $userRepository;
        $this->actionLogService = $actionLogService;
        $this->rankService = $rankService;
    }

    public function handle(GrantPointsCommand $command): array {
        $user_rank_dto    = $this->rankService->getUserRank($command->user_id);
        $rank_multiplier  = $this->getRankMultiplier($user_rank_dto->key);
        $final_multiplier = max( $rank_multiplier, $command->temp_multiplier );
        $points_to_grant  = floor( $command->base_points * $final_multiplier );
        $current_balance     = $this->userRepository->getPointsBalance($command->user_id);
        $new_balance         = $current_balance + $points_to_grant;
        $lifetime_points     = $this->userRepository->getLifetimePoints($command->user_id);
        $new_lifetime_points = $lifetime_points + $points_to_grant;
        $this->userRepository->savePointsAndRank($command->user_id, $new_balance, $new_lifetime_points, $user_rank_dto->key);
        $log_meta_data = [
            'description'        => $command->description,
            'points_change'      => $points_to_grant,
            'new_balance'        => $new_balance,
        ];
        $this->actionLogService->record( $command->user_id, 'points_granted', 0, $log_meta_data );
        Event::broadcast('user_points_granted', ['user_id' => $command->user_id]);
        return ['points_earned' => $points_to_grant, 'new_points_balance' => $new_balance];
    }

    private function getRankMultiplier(string $rankKey): float {
        $rank_post = get_page_by_path($rankKey, OBJECT, 'canna_rank');
        return $rank_post ? (float) get_post_meta($rank_post->ID, 'point_multiplier', true) : 1.0;
    }
}