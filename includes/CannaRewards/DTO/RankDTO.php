<?php
namespace CannaRewards\DTO;

use CannaRewards\Domain\ValueObjects\Points;
use CannaRewards\Domain\ValueObjects\RankKey;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Rank",
    description: "Represents a single rank or tier in the loyalty program."
)]
final class RankDTO {
    public function __construct(
        #[OA\Property(type: "string", example: "gold", description: "The unique, machine-readable key for the rank.")]
        public readonly RankKey $key,
        
        #[OA\Property(type: "string", example: "Gold", description: "The human-readable name of the rank.")]
        public readonly string $name,
        
        #[OA\Property(type: "integer", example: 5000, description: "The lifetime points required to achieve this rank.")]
        public readonly Points $pointsRequired,
        
        #[OA\Property(type: "number", format: "float", example: 1.5, description: "The point earning multiplier for this rank.")]
        public readonly float $pointMultiplier
    ) {}
}