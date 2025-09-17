<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\Points;
use CannaRewards\DTO\GrantPointsResultDTO;
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

    public function handle(GrantPointsCommand $command): GrantPointsResultDTO {
        // --- REFACTORED LOGIC ---
        // Get the user's current, full rank object from the single source of truth.
        // This removes the leaky, fragile direct DB calls from this handler.
        $user_rank_dto    = $this->rankService->getUserRank($command->userId);
        $rank_multiplier  = $user_rank_dto->pointMultiplier;
        // --- END REFACTORED LOGIC ---
        
        $final_multiplier = max( $rank_multiplier, $command->tempMultiplier );
        $points_to_grant  = floor( $command->basePoints->toInt() * $final_multiplier );
        
        $current_balance     = $this->userRepository->getPointsBalance($command->userId);
        $new_balance         = $current_balance + $points_to_grant;
        $lifetime_points     = $this->userRepository->getLifetimePoints($command->userId);
        $new_lifetime_points = $lifetime_points + $points_to_grant;
        
        $this->userRepository->savePointsAndRank($command->userId, $new_balance, $new_lifetime_points, (string)$user_rank_dto->key);
        
        $log_meta_data = [
            'description'        => $command->description,
            'points_change'      => $points_to_grant,
            'new_balance'        => $new_balance,
            'base_points'        => $command->basePoints->toInt(),
            'multiplier_applied' => $final_multiplier,
        ];
        $this->actionLogService->record( $command->userId->toInt(), 'points_granted', 0, $log_meta_data );
        
        $this->eventBus->broadcast('user_points_granted', ['user_id' => $command->userId->toInt()]);
        
        return new GrantPointsResultDTO(
            Points::fromInt($points_to_grant),
            Points::fromInt($new_balance)
        );
    }
}