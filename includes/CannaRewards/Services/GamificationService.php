<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Gamification Service
 *
 * Handles all logic related to processing achievements based on user actions.
 * Listens for events broadcast by other services.
 */
class GamificationService {
    private $economy_service;
    private $action_log_service;

    public function __construct() {
        $this->economy_service    = new EconomyService();
        $this->action_log_service = new ActionLogService();

        // Subscribe this service to all the events it cares about.
        Event::listen('product_scanned', [$this, 'handle_scan_event']);
        Event::listen('user_rank_changed', [$this, 'handle_rank_change_event']);
    }

    /**
     * Event handler for the 'product_scanned' event.
     *
     * @param array $payload The full, rich context object from the event broadcast.
     */
    public function handle_scan_event( array $payload ) {
        $user_id = $payload['user_id'] ?? 0;
        if ( empty($user_id) ) { return; }
        
        $this->check_and_process_event($user_id, 'product_scanned', $payload);
    }

    /**
     * Event handler for the 'user_rank_changed' event.
     *
     * @param array $payload The event data, containing user_id, old_rank, and new_rank.
     */
    public function handle_rank_change_event( array $payload ) {
        $user_id = $payload['user_id'] ?? 0;
        if ( empty($user_id) ) { return; }

        $this->check_and_process_event($user_id, 'user_rank_changed', $payload);
    }

    /**
     * The main logic engine. Checks all relevant achievements for a given event.
     *
     * @param int    $user_id The user who triggered the event.
     * @param string $event_name The name of the event (e.g., 'product_scanned').
     * @param array  $context Full contextual data for the event.
     * @return array A list of any unlocked achievements.
     */
    private function check_and_process_event( int $user_id, string $event_name, array $context = [] ): array {
        $unlocked_achievements = [];
        $achievements_to_check = $this->get_achievements_for_event( $event_name );

        foreach ( $achievements_to_check as $achievement ) {
            if ( $this->user_has_achievement( $user_id, $achievement->achievement_key ) ) {
                continue;
            }
            if ( ! $this->evaluate_conditions( $achievement->conditions, $context ) ) {
                continue;
            }

            $progress_key     = '_achievement_progress_' . $achievement->achievement_key;
            $current_progress = (int) get_user_meta( $user_id, $progress_key, true );
            $new_progress     = $current_progress + 1;
            update_user_meta( $user_id, $progress_key, $new_progress );

            if ( $new_progress >= (int) $achievement->trigger_count ) {
                $unlocked_details = $this->unlock_achievement( $user_id, $achievement );
                $unlocked_achievements[] = $unlocked_details;
            }
        }
        return $unlocked_achievements;
    }

    private function get_achievements_for_event( string $event_name ): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_achievements';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE is_active = 1 AND trigger_event = %s", $event_name ) );
    }

    private function user_has_achievement( int $user_id, string $achievement_key ): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_achievements';
        return ! is_null( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE user_id = %d AND achievement_key = %s", $user_id, $achievement_key ) ) );
    }

    private function evaluate_conditions( ?string $conditions_json, array $context ): bool {
        if ( empty( trim( (string) $conditions_json ) ) ) { return true; }
        $conditions = json_decode( $conditions_json, true );
        if ( ! is_array( $conditions ) ) { return true; }

        foreach ( $conditions as $condition ) {
            if ( ! isset( $condition['field'], $condition['operator'], $condition['value'] ) ) { continue; }
            $field_path = explode( '.', $condition['field'] );
            $actual_value = $context;
            foreach ( $field_path as $key ) {
                if ( ! isset( $actual_value[ $key ] ) ) { return false; }
                $actual_value = $actual_value[ $key ];
            }
            $match = false;
            switch ( $condition['operator'] ) {
                case 'is': $match = ( $actual_value == $condition['value'] ); break;
                case 'is_not': $match = ( $actual_value != $condition['value'] ); break;
                case '>': $match = ( (float) $actual_value > (float) $condition['value'] ); break;
                case '<': $match = ( (float) $actual_value < (float) $condition['value'] ); break;
            }
            if ( ! $match ) { return false; }
        }
        return true;
    }
    
    private function unlock_achievement( int $user_id, object $achievement ): array {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'canna_user_achievements', [ 'user_id' => $user_id, 'achievement_key' => $achievement->achievement_key, 'unlocked_at' => current_time( 'mysql', 1 ) ] );
        
        $points_reward = (int) $achievement->points_reward;
        if ( $points_reward > 0 ) {
            $this->economy_service->grant_points( $user_id, $points_reward, 'Achievement Unlocked: ' . $achievement->title );
        }

        $achievement_details = [ 'key' => $achievement->achievement_key, 'name' => $achievement->title, 'points_rewarded' => $points_reward ];
        $this->action_log_service->record( $user_id, 'achievement_unlocked', 0, $achievement_details );
        return $achievement_details;
    }
}