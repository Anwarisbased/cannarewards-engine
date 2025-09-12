<?php
namespace CannaRewards\DTO;

// A simple, public-property DTO for rank data.
final class RankDTO {
    public string $key;
    public string $name;
    public int $points;
    public float $point_multiplier;
}