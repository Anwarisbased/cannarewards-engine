<?php
namespace CannaRewards\DTO;

// A simple, public-property DTO for rank data.
final class RankDTO {
    public string $key;
    public string $name;
    // --- THE FIX ---
    // The RankService was trying to add this property dynamically.
    // By formally declaring it, we resolve the "Creation of dynamic property" error.
    public int $points;
    // --- END FIX ---
}