<?php
namespace CannaRewards\Services;

use CannaRewards\Infrastructure\WordPressApiWrapper; // <<<--- IMPORT THE WRAPPER

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Action Log Service
 */
class ActionLogService {
    private WordPressApiWrapper $wp; // <<<--- ADD THE WRAPPER PROPERTY

    public function __construct(WordPressApiWrapper $wp) // <<<--- INJECT THE WRAPPER
    {
        $this->wp = $wp;
    }

    /**
     * Records a user action to the log.
     */
    public function record(int $user_id, string $action_type, int $object_id = 0, array $meta_data = []): bool {
        // REFACTOR: Use the wrapper's dbInsert method
        $result = $this->wp->dbInsert(
            'canna_user_action_log',
            [
                'user_id'     => $user_id,
                'action_type' => $action_type,
                'object_id'   => $object_id,
                'meta_data'   => wp_json_encode($meta_data),
                'created_at'  => current_time('mysql', 1),
            ]
        );
        return (bool) $result;
    }

    /**
     * Fetches a user's point transaction history.
     */
    public function get_user_points_history( int $user_id, int $limit = 50 ): array {
        $table_name = 'canna_user_action_log'; // Keep this for clarity

        // REFACTOR: Use the wrapper's prepare and get_results methods
        $query = $this->wp->dbPrepare(
            "SELECT meta_data, created_at FROM {$this->wp->db->prefix}{$table_name} 
             WHERE user_id = %d 
             AND action_type IN ('points_granted', 'redeem')
             ORDER BY log_id DESC 
             LIMIT %d",
            $user_id,
            $limit
        );
        $results = $this->wp->dbGetResults($query);

        $history = [];
        if ( empty( $results ) ) {
            return $history;
        }

        foreach ( $results as $row ) {
            $meta = json_decode( $row->meta_data, true );
            if ( ! is_array( $meta ) || ! isset( $meta['points_change'] ) || ! isset( $meta['description'] ) ) {
                continue;
            }
            $history[] = [
                'points'      => (int) $meta['points_change'],
                'description' => $meta['description'],
                'log_date'    => $row->created_at,
            ];
        }
        return $history;
    }
}