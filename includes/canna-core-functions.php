<?php
/**
 * Core Procedural Functions
 *
 * This file contains essential, non-class-based helper functions used throughout
 * the CannaRewards plugin. It includes functions for retrieving user data,
 * accessing the rank structure, and registering custom post types.
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

/**
 * Gets the current points balance for a given user.
 *
 * @since 5.0.0
 *
 * @param int $user_id The ID of the user.
 * @return int The user's point balance, defaulting to 0.
 */
function get_user_points_balance($user_id) {
    $balance = get_user_meta($user_id, '_canna_points_balance', true);
    return empty($balance) ? 0 : (int) $balance;
}

/**
 * Gets the total lifetime points accumulated by a user.
 * This value is used to determine a user's rank.
 *
 * @since 5.0.0
 *
 * @param int $user_id The ID of the user.
 * @return int The user's lifetime points, defaulting to 0.
 */
function get_user_lifetime_points($user_id) {
    $lifetime_points = get_user_meta($user_id, '_canna_lifetime_points', true);
    return empty($lifetime_points) ? 0 : (int) $lifetime_points;
}

/**
 * @deprecated 2.1.0 Use CannaRewards\Services\RankService->getRankStructure() instead.
 */
function canna_get_rank_structure() {
    trigger_error('canna_get_rank_structure() is deprecated. Use RankService->getRankStructure() instead.', E_USER_DEPRECATED);
    // Return a default empty array to avoid breaking anything that might still call this.
    return [];
}

/**
 * @deprecated 2.1.0 Use CannaRewards\Services\RankService->getUserRank() instead.
 */
function get_user_current_rank($user_id) {
    trigger_error('get_user_current_rank() is deprecated. Use RankService->getUserRank() instead.', E_USER_DEPRECATED);
    // Return a default member rank to avoid breaking anything that might still call this.
    return ['key' => 'member', 'name' => 'Member'];
}


/**
 * Registers the 'canna_rank' Custom Post Type.
 * @since 5.0.0
 */
function canna_register_rank_post_type() {
    $labels = [ 'name' => _x('Ranks', 'Post Type General Name', 'canna-rewards'), /* ... other labels ... */ ];
    $args = [ 'label' => __('Rank', 'canna-rewards'), 'labels' => $labels, 'supports' => ['title', 'custom-fields'], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'canna_rewards_settings', 'menu_icon' => 'dashicons-star-filled', 'capability_type' => 'page' ];
    register_post_type('canna_rank', $args);
}

/**
 * Registers the 'canna_achievement' Custom Post Type.
 * @since 5.0.0
 */
function canna_register_achievement_post_type() {
    $labels = [ 'name' => _x('Achievements', 'Post Type General Name', 'canna-rewards'), /* ... other labels ... */ ];
    $args = [ 'label' => __('Achievement', 'canna-rewards'), 'labels' => $labels, 'supports' => ['title', 'editor', 'custom-fields'], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'canna_rewards_settings', 'menu_icon' => 'dashicons-awards', 'capability_type' => 'post' ];
    register_post_type('canna_achievement', $args);
}

/**
 * Synchronizes data from the 'canna_achievement' CPT to the 'canna_achievements' database table.
 * @since 5.0.0
 * @param int $post_id The ID of the post being saved.
 */
function canna_sync_achievement_cpt_to_table($post_id) {
    // ... (This function can be removed if the DB table is synced from the metabox save action directly)
}
// add_action('save_post_canna_achievement', 'canna_sync_achievement_cpt_to_table');

/**
 * Registers the 'canna_custom_field' Custom Post Type.
 * @since 2.0.0
 */
function canna_register_custom_field_post_type() {
    $labels = [ 'name' => _x('Custom Fields', 'Post Type General Name', 'canna-rewards'), /* ... other labels ... */ ];
    $args = [ 'label' => __('Custom Field', 'canna-rewards'), 'labels' => $labels, 'supports' => ['title'], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'canna_rewards_settings', 'capability_type' => 'page' ];
    register_post_type('canna_custom_field', $args);
}

/**
 * A helper function to invalidate the custom fields cache.
 * @since 2.0.0
 */
function canna_clear_custom_fields_cache() {
    delete_transient('canna_custom_fields_definition');
}

/**
 * Registers the 'canna_trigger' Custom Post Type.
 *
 * This CPT is the heart of the "If This, Then That" rules engine.
 * @since 2.0.0
 */
function canna_register_trigger_post_type() {
    $labels = [
        'name'          => _x('Triggers', 'Post Type General Name', 'canna-rewards'),
        'singular_name' => _x('Trigger', 'Post Type Singular Name', 'canna-rewards'),
        'menu_name'     => __('Triggers', 'canna-rewards'),
        'all_items'     => __('All Triggers', 'canna-rewards'),
        'add_new_item'  => __('Add New Trigger', 'canna-rewards'),
    ];
    $args = [
        'label'         => __('Trigger', 'canna-rewards'),
        'description'   => __('Defines automated actions based on user events.', 'canna-rewards'),
        'labels'        => $labels,
        'supports'      => ['title'],
        'hierarchical'  => false,
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'canna_rewards_settings',
        'capability_type' => 'page',
        'show_in_rest'  => false,
    ];
    register_post_type('canna_trigger', $args);
}

/**
 * A helper function to invalidate the triggers cache.
 * @since 2.0.0
 */
function canna_clear_triggers_cache() {
    delete_transient('canna_all_triggers');
}

/**
 * Retrieves all custom field definitions, with caching.
 * @since 2.1.0
 */
function canna_get_custom_fields_definitions(): array {
    $cached_fields = get_transient('canna_custom_fields_definition');
    if (is_array($cached_fields)) {
        return $cached_fields;
    }

    $fields = [];
    $args = [
        'post_type'      => 'canna_custom_field',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ];
    $field_posts = get_posts($args);

    foreach ($field_posts as $post) {
        $options_raw = get_post_meta($post->ID, 'options', true);
        $fields[] = [
            'key'       => get_post_meta($post->ID, 'meta_key', true),
            'label'     => get_the_title($post->ID),
            'type'      => get_post_meta($post->ID, 'field_type', true),
            'options'   => !empty($options_raw) ? preg_split('/\\r\\n|\\r|\\n/', $options_raw) : [],
            'display'   => (array) get_post_meta($post->ID, 'display_location', true),
        ];
    }

    set_transient('canna_custom_fields_definition', $fields, 12 * HOUR_IN_SECONDS);
    return $fields;
}