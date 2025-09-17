<?php
namespace CannaRewards\DTO;

use CannaRewards\Domain\ValueObjects\OrderId;
use CannaRewards\Domain\ValueObjects\Points;

final class RedeemRewardResultDTO {
    public function __construct(
        public readonly OrderId $orderId,
        public readonly Points $newPointsBalance
    ) {}
}