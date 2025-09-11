<?php
/**
 * Core Procedural Functions
 *
 * This file contains essential, non-class-based helper functions used throughout
 * the CannaRewards plugin. It includes functions for registering custom post types.
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

// NOTE: All data-fetching global functions have been removed and their logic
// has been migrated to dedicated, injectable repository classes. This file
// now only contains bootstrap code (like CPT registration) that hooks into WordPress.

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
 * Registers the 'canna_custom_field' Custom Post Type.
 * @since 2.0.0
 */
function canna_register_custom_field_post_type() {
    $labels = [ 'name' => _x('Custom Fields', 'Post Type General Name', 'canna-rewards'), /* ... other labels ... */ ];
    $args = [ 'label' => __('Custom Field', 'canna-rewards'), 'labels' => $labels, 'supports' => ['title'], 'hierarchical' => false, 'public' => false, 'show_ui' => true, 'show_in_menu' => 'canna_rewards_settings', 'capability_type' => 'page' ];
    register_post_type('canna_custom_field', $args);
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