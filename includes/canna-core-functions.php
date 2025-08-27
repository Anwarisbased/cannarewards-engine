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
    // --- START: Caching Logic ---
    $cached_ranks = get_transient('canna_rank_structure');
    if (false !== $cached_ranks) {
        return $cached_ranks;
    }
    // --- END: Caching Logic ---

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

    // Ensure ranks are always sorted by points descending, regardless of query order.
    uasort(
        $ranks_for_api,
        function ($a, $b) {
            return $b['points'] - $a['points'];
        }
    );

    // Cache the result for 12 hours. It will be cleared manually on post update.
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
 *
 * This CPT is used to define the different membership tiers for the rewards program.
 * It's intentionally non-public and primarily for admin management.
 * Hooked into 'init' from the main plugin loader.
 *
 * @since 5.0.0
 */
function canna_register_rank_post_type() {
    $labels = [
        'name'                  => _x('Ranks', 'Post Type General Name', 'canna-rewards'),
        'singular_name'         => _x('Rank', 'Post Type Singular Name', 'canna-rewards'),
        'menu_name'             => __('Ranks', 'canna-rewards'),
        'name_admin_bar'        => __('Rank', 'canna-rewards'),
        'all_items'             => __('All Ranks', 'canna-rewards'),
        'add_new_item'          => __('Add New Rank', 'canna-rewards'),
        'add_new'               => __('Add New', 'canna-rewards'),
        'new_item'              => __('New Rank', 'canna-rewards'),
        'edit_item'             => __('Edit Rank', 'canna-rewards'),
        'update_item'           => __('Update Rank', 'canna-rewards'),
        'view_item'             => __('View Rank', 'canna-rewards'),
        'search_items'          => __('Search Rank', 'canna-rewards'),
        'not_found'             => __('Not found', 'canna-rewards'),
        'not_found_in_trash'    => __('Not found in Trash', 'canna-rewards'),
    ];
    $args = [
        'label'                 => __('Rank', 'canna-rewards'),
        'description'           => __('Membership Tiers for the Rewards Program', 'canna-rewards'),
        'labels'                => $labels,
        'supports'              => ['title', 'custom-fields'],
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 21,
        'menu_icon'             => 'dashicons-star-filled',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'page',
        'show_in_rest'          => true, // Exposes CPT data to REST API, which can be useful.
    ];
    register_post_type('canna_rank', $args);
}

/**
 * Registers the 'canna_achievement' Custom Post Type.
 *
 * This CPT is used to define achievements for the rewards program.
 * Hooked into 'init' from the main plugin loader.
 *
 * @since 5.0.0
 */
function canna_register_achievement_post_type() {
    $labels = [
        'name'                  => _x('Achievements', 'Post Type General Name', 'canna-rewards'),
        'singular_name'         => _x('Achievement', 'Post Type Singular Name', 'canna-rewards'),
        'menu_name'             => __('Achievements', 'canna-rewards'),
        'name_admin_bar'        => __('Achievement', 'canna-rewards'),
        'all_items'             => __('All Achievements', 'canna-rewards'),
        'add_new_item'          => __('Add New Achievement', 'canna-rewards'),
        'add_new'               => __('Add New', 'canna-rewards'),
        'new_item'              => __('New Achievement', 'canna-rewards'),
        'edit_item'             => __('Edit Achievement', 'canna-rewards'),
        'update_item'           => __('Update Achievement', 'canna-rewards'),
        'view_item'             => __('View Achievement', 'canna-rewards'),
        'search_items'          => __('Search Achievement', 'canna-rewards'),
        'not_found'             => __('Not found', 'canna-rewards'),
        'not_found_in_trash'    => __('Not found in Trash', 'canna-rewards'),
    ];
    $args = [
        'label'                 => __('Achievement', 'canna-rewards'),
        'description'           => __('Achievements for the Rewards Program', 'canna-rewards'),
        'labels'                => $labels,
        'supports'              => ['title', 'editor', 'custom-fields'], // 'editor' for description
        'hierarchical'          => false,
        'public'                => false, // Not publicly queryable
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 22, // Below Ranks
        'menu_icon'             => 'dashicons-awards',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    ];
    register_post_type('canna_achievement', $args);
}
add_action('init', 'canna_register_achievement_post_type');

/**
 * Synchronizes data from the 'canna_achievement' CPT to the 'canna_achievements' database table.
 *
 * This function is hooked to 'save_post_canna_achievement' and ensures that
 * achievement data entered via the WordPress admin is mirrored in our custom table.
 * It uses an INSERT ... ON DUPLICATE KEY UPDATE statement to handle both new
 * achievements and updates to existing ones based on the unique 'achievement_key'.
 *
 * @since 5.0.0
 *
 * @param int $post_id The ID of the post being saved.
 */
function canna_sync_achievement_cpt_to_table($post_id) {
    // If this is an autosave, our form has not been submitted, so we don't want to do anything.
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Only proceed for 'canna_achievement' post type.
    if (get_post_type($post_id) !== 'canna_achievement') {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'canna_achievements';

    // Retrieve data from the CPT and its custom fields.
    $post_title      = get_the_title($post_id);
    $post_content    = get_post_field('post_content', $post_id); // Use post_content for description
    $achievement_key = get_post_meta($post_id, 'achievement_key', true);
    $type            = get_post_meta($post_id, 'type', true);
    $points_reward   = get_post_meta($post_id, 'points_reward', true);
    $rarity          = get_post_meta($post_id, 'rarity', true);
    $icon_url        = get_post_meta($post_id, 'icon_url', true);
    $is_active       = get_post_meta($post_id, 'is_active', true);

    // Ensure achievement_key is present, it's our primary key in the custom table.
    if (empty($achievement_key)) {
        // Optionally, log an error or set a default if key is missing.
        // For now, we'll just return to prevent inserting invalid data.
        error_log('CannaRewards: Attempted to sync achievement without a key for Post ID: ' . $post_id);
        return;
    }

    // Prepare data for insertion/update.
    $data = [
        'achievement_key' => $achievement_key,
        'type'            => sanitize_text_field($type),
        'title'           => sanitize_text_field($post_title),
        'description'     => wp_kses_post($post_content), // Sanitize content for description
        'points_reward'   => absint($points_reward),
        'rarity'          => sanitize_text_field($rarity),
        'icon_url'        => esc_url_raw($icon_url),
        'is_active'       => absint($is_active),
    ];

    // Define format for data.
    $format = [
        '%s', // achievement_key
        '%s', // type
        '%s', // title
        '%s', // description
        '%d', // points_reward
        '%s', // rarity
        '%s', // icon_url
        '%d', // is_active
    ];

    // Perform INSERT ... ON DUPLICATE KEY UPDATE
    // This handles both new insertions and updates to existing rows based on achievement_key.
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO `{$table_name}`
            (`achievement_key`, `type`, `title`, `description`, `points_reward`, `rarity`, `icon_url`, `is_active`)
            VALUES (%s, %s, %s, %s, %d, %s, %s, %d)
            ON DUPLICATE KEY UPDATE
            `type` = VALUES(`type`),
            `title` = VALUES(`title`),
            `description` = VALUES(`description`),
            `points_reward` = VALUES(`points_reward`),
            `rarity` = VALUES(`rarity`),
            `icon_url` = VALUES(`icon_url`),
            `is_active` = VALUES(`is_active`)",
            $data['achievement_key'], $data['type'], $data['title'], $data['description'], $data['points_reward'], $data['rarity'], $data['icon_url'], $data['is_active'],
        )
    );
}
add_action('save_post_canna_achievement', 'canna_sync_achievement_cpt_to_table');