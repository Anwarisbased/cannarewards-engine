<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Domain\ValueObjects\Points;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for granting points to a user.
 */
final class GrantPointsCommand {
    public UserId $userId;
    public Points $basePoints;
    public string $description;
    public float $tempMultiplier;

    public function __construct(
        UserId $userId,
        Points $basePoints,
        string $description,
        float $tempMultiplier = 1.0
    ) {
        $this->userId = $userId;
        $this->basePoints = $basePoints;
        $this->description = $description;
        $this->tempMultiplier = $tempMultiplier;
    }
}