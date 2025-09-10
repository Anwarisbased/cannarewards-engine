<?php
require_once dirname(__DIR__, 4) . '/wp-load.php';

// Directly query for canna_rank posts
$args = [
    'post_type'      => 'canna_rank',
    'posts_per_page' => -1,
    'post_status'    => 'any', // Check all statuses
];

$rank_posts = new WP_Query($args);

echo "Found " . count($rank_posts->posts) . " rank posts:\n";

foreach ($rank_posts->posts as $post) {
    echo "ID: " . $post->ID . " | Title: " . $post->post_title . " | Name: " . $post->post_name . " | Status: " . $post->post_status . "\n";
    
    // Get the meta values
    $points_required = get_post_meta($post->ID, 'points_required', true);
    $point_multiplier = get_post_meta($post->ID, 'point_multiplier', true);
    
    echo "  Points Required: " . $points_required . "\n";
    echo "  Point Multiplier: " . $point_multiplier . "\n";
    echo "  ---\n";
}

// Also check the transient
echo "\nChecking transient 'canna_rank_structure_dtos':\n";
$cached_ranks = get_transient('canna_rank_structure_dtos');
if ($cached_ranks) {
    echo "Cached ranks found:\n";
    foreach ($cached_ranks as $rank) {
        echo "Key: " . $rank->key . " | Name: " . $rank->name . " | Points: " . $rank->points . "\n";
    }
} else {
    echo "No cached ranks found.\n";
}