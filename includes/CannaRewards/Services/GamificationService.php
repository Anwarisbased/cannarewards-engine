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
 * This service listens for events broadcast by other services and evaluates achievement rules.
 */
class GamificationService {
    private $economy_service;
    private $action_log_service;

    public function __construct() {
        $this->economy_service    = new EconomyService();
        $this->action_log_service = new ActionLogService();

        // Subscribe this service to all the events it cares about.
        Event::listen('product_scanned', [$this, 'handle_event']);
        Event::listen('user_rank_changed', [$this, 'handle_event']);
        Event::listen('reward_redeemed', [$this, 'handle_event']);
    }

    /**
     * Generic event handler that triggers the main processing logic.
     *
     * @param array $payload The full context from the event broadcast.
     */
    public function handle_event( array $payload ) {
        // All event payloads must contain the user_id.
        $user_id = $payload['user_id'] ?? 0;
        if ( empty($user_id) ) {
            return;
        }
        
        // Determine the event name from the currently running WordPress hook.
        $event_name = current_filter();

        $this->check_and_process_event($user_id, $event_name, $payload);
    }

    /**
     * Fetches achievements for a specific event and evaluates them for a user.
     *
     * @param int    $user_id The user who triggered the event.
     * @param string $event_name The name of the event (e.g., 'product_scanned').
     * @param array  $context Full contextual data for the event.
     */
    private function check_and_process_event( int $user_id, string $event_name, array $context = [] ) {
        $achievements_to_check = $this->get_achievements_for_event( $event_name );
        $user_unlocked_keys    = $this->get_user_unlocked_achievement_keys( $user_id );

        foreach ( $achievements_to_check as $achievement ) {
            if ( in_array( $achievement->achievement_key, $user_unlocked_keys, true ) ) {
                continue; // User already has this achievement.
            }

            if ( $this->evaluate_conditions( $achievement, $user_id, $context ) ) {
                $this->unlock_achievement( $user_id, $achievement );
            }
        }
    }
    
    /**
     * Evaluates the conditions for an achievement to be unlocked.
     *
     * @param object $achievement The achievement rule object from the database.
     * @param int    $user_id The user ID.
     * @param array  $context The event payload for context-aware checks.
     * @return bool  True if all conditions are met.
     */
    private function evaluate_conditions( object $achievement, int $user_id, array $context ): bool {
        global $wpdb;
        $log_table = $wpdb->prefix . 'canna_user_action_log';

        // 1. Check the trigger count.
        $action_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(log_id) FROM {$log_table} WHERE user_id = %d AND action_type = %s",
            $user_id,
            $achievement->trigger_event
        ));

        if ($action_count < (int) $achievement->trigger_count) {
            return false;
        }

        // 2. Evaluate JSON conditions.
        if ( empty( trim( (string) $achievement->conditions ) ) ) {
            return true; // No JSON conditions, so trigger count was enough.
        }
        $conditions = json_decode( $achievement->conditions, true );
        if ( ! is_array( $conditions ) ) {
            return true; // Malformed JSON, fail open.
        }

        foreach ( $conditions as $condition ) {
            if ( ! isset( $condition['field'], $condition['operator'], $condition['value'] ) ) { continue; }
            
            $field_path = explode( '.', $condition['field'] );
            $actual_value = $context;
            
            foreach ( $field_path as $key ) {
                if ( ! isset( $actual_value[ $key ] ) ) {
                    return false; // The required key does not exist in the context.
                }
                $actual_value = $actual_value[ $key ];
            }

            // Perform the comparison.
            $match = false;
            switch ( $condition['operator'] ) {
                case 'is': $match = ( $actual_value == $condition['value'] ); break;
                case 'is_not': $match = ( $actual_value != $condition['value'] ); break;
                case '>': $match = ( (float) $actual_value > (float) $condition['value'] ); break;
                case '<': $match = ( (float) $actual_value < (float) $condition['value'] ); break;
            }
            if ( ! $match ) {
                return false; // If any condition fails, the whole group fails.
            }
        }
        
        return true; // All conditions passed.
    }

    /**
     * Awards an achievement to a user and grants points.
     */
    private function unlock_achievement( int $user_id, object $achievement ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'canna_user_achievements', [ 'user_id' => $user_id, 'achievement_key' => $achievement->achievement_key, 'unlocked_at' => current_time( 'mysql', 1 ) ] );
        
        $points_reward = (int) $achievement->points_reward;
        if ( $points_reward > 0 ) {
            $this->economy_service->grant_points( $user_id, $points_reward, 'Achievement Unlocked: ' . $achievement->title );
        }

        $achievement_details = [ 'key' => $achievement->achievement_key, 'name' => $achievement->title, 'points_rewarded' => $points_reward ];
        $this->action_log_service->record( $user_id, 'achievement_unlocked', 0, $achievement_details );
        
        // FUTURE: Broadcast 'achievement_unlocked' event here for other services to listen to.
        // Event::broadcast('achievement_unlocked', ['user_id' => $user_id, 'achievement' => $achievement_details]);
    }

    /**
     * Retrieves all active achievements for a given event trigger.
     */
    private function get_achievements_for_event( string $event_name ): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_achievements';
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE is_active = 1 AND trigger_event = %s", $event_name ) );
    }

    /**
     * Gets an array of achievement keys a user has already unlocked.
     */
    private function get_user_unlocked_achievement_keys( int $user_id ): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_achievements';
        return $wpdb->get_col( $wpdb->prepare( "SELECT achievement_key FROM {$table_name} WHERE user_id = %d", $user_id ) );
    }
}