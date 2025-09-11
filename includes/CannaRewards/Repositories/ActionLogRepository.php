<?php
namespace CannaRewards\Repositories;

use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Action Log Repository
 * Handles all data access logic for the user action log table.
 */
class ActionLogRepository {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    public function countUserActions(int $user_id, string $action_type): int {
        $table_name = 'canna_user_action_log';
        $query = $this->wp->dbPrepare(
            "SELECT COUNT(log_id) FROM {$this->wp->db->prefix}{$table_name} WHERE user_id = %d AND action_type = %s",
            $user_id,
            $action_type
        );

        return (int) $this->wp->dbGetVar($query);
    }
}