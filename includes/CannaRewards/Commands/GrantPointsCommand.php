<?php
namespace CannaRewards\Commands;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for granting points to a user.
 */
final class GrantPointsCommand {
    public int $user_id;
    public int $base_points;
    public string $description;
    public float $temp_multiplier;

    public function __construct(
        int $user_id,
        int $base_points,
        string $description,
        float $temp_multiplier = 1.0
    ) {
        $this->user_id = $user_id;
        $this->base_points = $base_points;
        $this->description = $description;
        $this->temp_multiplier = $temp_multiplier;
    }
}