<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Clear rank cache first
delete_transient('canna_rank_structure_dtos_v2');

// Get user repo and rank service
$userRepo = new \CannaRewards\Repositories\UserRepository();
$rankService = new \CannaRewards\Services\RankService($userRepo);

// Get rank structure
$ranks = $rankService->getRankStructure();

echo "Rank structure:\n";
foreach ($ranks as $rank) {
    echo "Key: " . $rank->key . ", Name: " . $rank->name . ", Points Required: " . $rank->pointsRequired->toInt() . "\n";
}

// Test with a user with 5200 lifetime points
echo "\nTesting user with 5200 lifetime points:\n";
$testUserId = \CannaRewards\Domain\ValueObjects\UserId::fromInt(1);
$userRank = $rankService->getUserRank($testUserId);
echo "User rank: " . $userRank->key . " (" . $userRank->name . ")\n";
?>