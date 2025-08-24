<?php
/**
 * Handles database-related functionality, like table creation on activation.
 */
class Canna_DB {

    /**
     * Plugin activation hook. Creates custom database tables.
     * This method is called from the main plugin loader file.
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
            points int(11) NOT NULL,
            sku varchar(100) DEFAULT '' NOT NULL,
            is_used tinyint(1) DEFAULT 0 NOT NULL,
            user_id bigint(20) unsigned,
            claimed_at datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        dbDelta($sql);

        // Table for points transaction log
        $log_table_name = $wpdb->prefix . 'canna_points_log';
        $log_sql = "CREATE TABLE $log_table_name (
            log_id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL,
            description varchar(255) NOT NULL,
            log_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (log_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($log_sql);
    }
}