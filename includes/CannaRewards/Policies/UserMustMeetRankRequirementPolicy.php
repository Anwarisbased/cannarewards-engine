<?php
namespace CannaRewards\Policies;

use CannaRewards\Commands\RedeemRewardCommand;
use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Services\RankService;
use Exception;

class UserMustMeetRankRequirementPolicy implements AuthorizationPolicyInterface {
    public function __construct(
        private ProductRepository $productRepo,
        private RankService $rankService
    ) {}

    public function check(UserId $userId, object $command): void {
        if (!$command instanceof RedeemRewardCommand) {
            return;
        }
        
        $requiredRankKey = $this->productRepo->getRequiredRank($command->productId);
        if ($requiredRankKey === null) {
            return; // No rank required for this product.
        }

        $requiredRank = $this->rankService->getRankByKey($requiredRankKey);
        $userLifetimePoints = $this->rankService->getUserLifetimePoints($userId);

        if ($userLifetimePoints < $requiredRank->pointsRequired->toInt()) {
            throw new Exception("You must be rank '{$requiredRank->name}' or higher to redeem this item.", 403);
        }
    }
}