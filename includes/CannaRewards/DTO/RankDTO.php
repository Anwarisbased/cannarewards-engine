<?php
namespace CannaRewards\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Rank",
    description: "Represents a single rank or tier in the loyalty program."
)]
final class RankDTO {
    #[OA\Property(type: "string", example: "gold", description: "The unique, machine-readable key for the rank.")]
    public string $key;
    
    #[OA\Property(type: "string", example: "Gold", description: "The human-readable name of the rank.")]
    public string $name;
    
    #[OA\Property(type: "integer", example: 5000, description: "The lifetime points required to achieve this rank.")]
    public int $points;
    
    #[OA\Property(type: "number", format: "float", example: 1.5, description: "The point earning multiplier for this rank.")]
    public float $point_multiplier;
}