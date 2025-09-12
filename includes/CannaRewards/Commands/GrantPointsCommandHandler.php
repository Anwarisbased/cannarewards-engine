<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\RankService;
use CannaRewards\Includes\EventBusInterface;

final class GrantPointsCommandHandler {
    private UserRepository $userRepository;
    private ActionLogService $actionLogService;
    private RankService $rankService;
    private EventBusInterface $eventBus;

    public function __construct(
        UserRepository $userRepository,
        ActionLogService $actionLogService,
        RankService $rankService,
        EventBusInterface $eventBus
    ) {
        $this->userRepository = $userRepository;
        $this->actionLogService = $actionLogService;
        $this->rankService = $rankService;
        $this->eventBus = $eventBus;
    }

    public function handle(GrantPointsCommand $command): array {
        // --- REFACTORED LOGIC ---
        // Get the user's current, full rank object from the single source of truth.
        // This removes the leaky, fragile direct DB calls from this handler.
        $user_rank_dto    = $this->rankService->getUserRank($command->user_id);
        $rank_multiplier  = $user_rank_dto->point_multiplier;
        // --- END REFACTORED LOGIC ---
        
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
            'base_points'        => $command->base_points,
            'multiplier_applied' => $final_multiplier,
        ];
        $this->actionLogService->record( $command->user_id, 'points_granted', 0, $log_meta_data );
        
        $this->eventBus->broadcast('user_points_granted', ['user_id' => $command->user_id]);
        
        return ['points_earned' => $points_to_grant, 'new_points_balance' => $new_balance];
    }
}