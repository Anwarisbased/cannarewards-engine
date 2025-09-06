<?php
namespace CannaRewards\Repositories;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Achievement Repository
 *
 * Handles all data access for achievement definitions and user progress.
 */
class AchievementRepository {
    private static $request_cache = [];

    /**
     * Retrieves all active achievements that are triggered by a specific event.
     * Caches results for the duration of a single request to prevent duplicate queries.
     */
    public function findByTriggerEvent(string $event_name): array {
        if (isset(self::$request_cache[$event_name])) {
            return self::$request_cache[$event_name];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_achievements';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE is_active = 1 AND trigger_event = %s",
            $event_name
        ));

        self::$request_cache[$event_name] = $results;
        return $results;
    }

    /**
     * Gets an array of achievement keys a user has already unlocked.
     */
    public function getUnlockedKeysForUser(int $user_id): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_achievements';
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT achievement_key FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Persists an unlocked achievement for a user.
     */
    public function saveUnlockedAchievement(int $user_id, string $achievement_key): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_user_achievements';
        
        $wpdb->insert($table_name, [
            'user_id'         => $user_id,
            'achievement_key' => $achievement_key,
            'unlocked_at'     => current_time('mysql', 1)
        ]);
    }
}