<?php
namespace CannaRewards\DTO;

use CannaRewards\Domain\ValueObjects\Points;

// This DTO is for internal use, so it doesn't need OpenAPI annotations.
final class GrantPointsResultDTO {
    public function __construct(
        public readonly Points $pointsEarned,
        public readonly Points $newPointsBalance
    ) {}
}