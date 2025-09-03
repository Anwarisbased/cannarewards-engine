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
 * Retrieves the defined rank structure from the 'canna_rank' Custom Post Type.
 *
 * Queries all 'canna_rank' posts, formats them into a structured array,
 * adds the default 'Member' rank, and sorts them by points requirement in descending order.
 * The results are cached in a transient for performance and invalidated when ranks are updated.
 *
 * @since 5.0.0
 *
 * @return array An associative array of rank data, keyed by rank slug.
 */
function canna_get_rank_structure() {
    $cached_ranks = get_transient('canna_rank_structure');
    if (false !== $cached_ranks && is_array($cached_ranks)) {
        return $cached_ranks;
    }

    $ranks_for_api = [];
    $args          = [
        'post_type'      => 'canna_rank',
        'posts_per_page' => -1,
        'meta_key'       => 'points_required',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ];
    $rank_posts    = new WP_Query($args);

    if ($rank_posts->have_posts()) {
        while ($rank_posts->have_posts()) {
            $rank_posts->the_post();
            $post_id       = get_the_ID();
            $key           = get_post_field('post_name', $post_id);
            $benefits_text = get_post_meta($post_id, 'benefits', true);
            $benefits      = [];
            if (!empty($benefits_text)) {
                $benefits = preg_split('/\\r\\n|\\r|\\n/', $benefits_text, -1, PREG_SPLIT_NO_EMPTY);
            }
            $ranks_for_api[$key] = [
                'name'     => get_the_title(),
                'points'   => (int) get_post_meta($post_id, 'points_required', true),
                'benefits' => $benefits,
            ];
        }
    }
    wp_reset_postdata();

    // Manually add the base 'Member' rank.
    $ranks_for_api['member'] = [
        'name'     => 'Member',
        'points'   => 0,
        'benefits' => ['Earn points on every scan to start unlocking rewards.'],
    ];

    uasort(
        $ranks_for_api,
        function ($a, $b) {
            return $b['points'] - $a['points'];
        }
    );

    set_transient('canna_rank_structure', $ranks_for_api, 12 * HOUR_IN_SECONDS);

    return $ranks_for_api;
}

/**
 * Determines the current rank of a user based on their lifetime points.
 *
 * @since 5.0.0
 *
 * @param int $user_id The ID of the user.
 * @return array An array containing the 'key' (slug) and 'name' of the user's current rank.
 */
function get_user_current_rank($user_id) {
    $lifetime_points = get_user_lifetime_points($user_id);
    $ranks           = canna_get_rank_structure();

    // Iterate through ranks (from highest to lowest) and return the first one the user qualifies for.
    foreach ($ranks as $rank_key => $rank_data) {
        if ($lifetime_points >= $rank_data['points']) {
            return ['key' => $rank_key, 'name' => $rank_data['name']];
        }
    }

    // Default fallback rank if something goes wrong or no ranks are defined.
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