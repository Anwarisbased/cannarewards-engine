<?php
namespace CannaRewards\Includes;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Handles database-related functionality, like table creation on activation.
 */
class DB {

    /**
     * Plugin activation hook. Creates/Updates custom database tables.
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table for reward codes
        $table_name = $wpdb->prefix . 'canna_reward_codes';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            sku varchar(100) DEFAULT '' NOT NULL,
            batch_id varchar(255) DEFAULT '' NOT NULL,
            is_used tinyint(1) DEFAULT 0 NOT NULL,
            user_id bigint(20) unsigned,
            claimed_at datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        dbDelta($sql);

        // Table for achievements
        $achievements_table_name = $wpdb->prefix . 'canna_achievements';
        $achievements_sql = "CREATE TABLE `{$achievements_table_name}` (
            `achievement_key` varchar(100) NOT NULL,
            `type` varchar(50) NOT NULL DEFAULT '' COMMENT 'Categorization for UI filtering',
            `title` varchar(255) NOT NULL,
            `description` text NOT NULL,
            `points_reward` int(11) DEFAULT 0 NOT NULL,
            `rarity` varchar(50) DEFAULT 'common' NOT NULL,
            `icon_url` varchar(255) DEFAULT '' NOT NULL,
            `is_active` tinyint(1) DEFAULT 1 NOT NULL,
            `trigger_event` varchar(100) NOT NULL DEFAULT '' COMMENT 'e.g., product_scanned',
            `trigger_count` int(11) NOT NULL DEFAULT 1,
            `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON-encoded array of conditions',
            PRIMARY KEY  (`achievement_key`),
            KEY `is_active` (`is_active`),
            KEY `trigger_event` (`trigger_event`)
        ) {$charset_collate};";
        dbDelta($achievements_sql);

        // Table for user unlocked achievements
        $user_achievements_table_name = $wpdb->prefix . 'canna_user_achievements';
        $user_achievements_sql = "CREATE TABLE `{$user_achievements_table_name}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `achievement_key` varchar(100) NOT NULL,
            `unlocked_at` datetime NOT NULL,
            PRIMARY KEY  (`id`),
            UNIQUE KEY `user_achievement` (`user_id`, `achievement_key`)
        ) {$charset_collate};";
        dbDelta($user_achievements_sql);

        // Table for user action log
        $action_log_table_name = $wpdb->prefix . 'canna_user_action_log';
        $action_log_sql = "CREATE TABLE `{$action_log_table_name}` (
            `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `action_type` varchar(50) NOT NULL,
            `object_id` bigint(20) unsigned DEFAULT 0,
            `meta_data` longtext,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`log_id`),
            KEY `user_action` (`user_id`, `action_type`)
        ) {$charset_collate};";
        dbDelta($action_log_sql);
    }
}