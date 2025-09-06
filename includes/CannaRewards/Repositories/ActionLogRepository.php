<?php
namespace CannaRewards\Repositories;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Action Log Repository
 *
 * Handles all data access logic for the user action log table.
 */
class ActionLogRepository {

    /**
     * Counts the number of times a user has performed a specific action.
     */
    public function countUserActions(int $user_id, string $action_type): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_action_log';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(log_id) FROM {$table_name} WHERE user_id = %d AND action_type = %s",
            $user_id,
            $action_type
        ));
    }
}