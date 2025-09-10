<?php
// Enable Xdebug if available
if (function_exists('xdebug_enable')) {
    xdebug_enable();
}

require_once dirname(__DIR__, 4) . '/wp-load.php';

// Force Xdebug session
if (function_exists('xdebug_is_enabled')) {
    ini_set('xdebug.remote_autostart', 1);
}

echo "Starting rank debugging...\n";

// Clear any cached rank data
delete_transient('canna_rank_structure_dtos');
echo "Cleared rank cache.\n";

// Debug the rank structure directly
echo "Querying for rank posts...\n";

$args = [
    'post_type'      => 'canna_rank',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
];

$rank_posts = new WP_Query($args);
echo "Found " . $rank_posts->post_count . " rank posts.\n";

if ($rank_posts->post_count > 0) {
    echo "Rank posts found:\n";
    foreach ($rank_posts->posts as $post) {
        echo "  - ID: " . $post->ID . ", Title: " . $post->post_title . ", Name: " . $post->post_name . "\n";
        $points_required = get_post_meta($post->ID, 'points_required', true);
        echo "    Points required: " . ($points_required ?: 'NOT SET') . "\n";
    }
} else {
    echo "NO RANK POSTS FOUND - This is likely the issue!\n";
    
    // Check for any rank posts regardless of status
    echo "Checking for rank posts with any status...\n";
    $args_any = [
        'post_type'      => 'canna_rank',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ];
    $rank_posts_any = new WP_Query($args_any);
    echo "Found " . $rank_posts_any->post_count . " rank posts with any status.\n";
    
    foreach ($rank_posts_any->posts as $post) {
        echo "  - ID: " . $post->ID . ", Title: " . $post->post_title . ", Name: " . $post->post_name . ", Status: " . $post->post_status . "\n";
    }
}

// Test the RankService
echo "\nTesting RankService...\n";
try {
    $userRepo = new \CannaRewards\Repositories\UserRepository();
    $rankService = new \CannaRewards\Services\RankService($userRepo);
    
    echo "Calling getRankStructure()...\n";
    // Set a short timeout to prevent hanging
    set_time_limit(10);
    
    $ranks = $rankService->getRankStructure();
    echo "Rank structure retrieved successfully.\n";
    echo "Ranks found: " . count($ranks) . "\n";
    
    foreach ($ranks as $rank) {
        echo "  - Key: " . $rank->key . ", Name: " . $rank->name . ", Points: " . $rank->points . "\n";
    }
    
    // Test with a specific user
    echo "\nTesting getUserRank with user ID 1...\n";
    $user_rank = $rankService->getUserRank(1);
    echo "User rank: " . $user_rank->key . " (" . $user_rank->name . ")\n";
    
} catch (Exception $e) {
    echo "ERROR in RankService: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR in RankService: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "Debug script completed.\n";
?>