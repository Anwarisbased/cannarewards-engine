<?php
namespace CannaRewards\Services;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Action Log Service
 */
class ActionLogService {
    /**
     * Records a user action to the log.
     */
    public function record(int $user_id, string $action_type, int $object_id = 0, array $meta_data = []): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_action_log';

        $result = $wpdb->insert(
            $table_name,
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_action_log';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_data, created_at FROM {$table_name} 
                 WHERE user_id = %d 
                 AND JSON_EXISTS(meta_data, '$.points_change')
                 ORDER BY log_id DESC 
                 LIMIT %d",
                $user_id,
                $limit
            )
        );

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