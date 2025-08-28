<?php
/**
 * Handles the final awarding logic for CannaRewards achievements.
 *
 * This class is responsible for the final database write when a user unlocks
 * an achievement and for awarding any associated points. It is called by the
 * Canna_Achievement_Engine after all rules and conditions have been met.
 *
 * @package CannaRewards
 * @subpackage Gamification
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Achievement_Handler {

    /**
     * Awards an achievement to a user and grants points.
     * This is the single, authoritative method for awarding an achievement. It assumes
     * all prerequisite checks (e.g., if the user already has it) have been done by the engine.
     *
     * @param int    $user_id             The ID of the user.
     * @param object $achievement_details An object containing the achievement data from the database.
     * @return bool True if the achievement was awarded, false on failure.
     */
    public static function award_achievement($user_id, $achievement_details) {
        global $wpdb;
        $user_achievements_table = $wpdb->prefix . 'canna_user_achievements';

        if (!isset($achievement_details->achievement_key)) {
            return false;
        }

        // Award the achievement by inserting into the user's record.
        $inserted = $wpdb->insert(
            $user_achievements_table,
            [
                'user_id'         => $user_id,
                'achievement_key' => $achievement_details->achievement_key,
                'unlocked_at'     => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        if ($inserted) {
            // If the achievement has points, award them.
            if ($achievement_details->points_reward > 0) {
                Canna_Points_Handler::add_user_points(
                    $user_id,
                    (int) $achievement_details->points_reward,
                    sprintf(__('Achievement Unlocked: %s', 'canna-rewards'), $achievement_details->title)
                );
            }
            return true;
        }

        return false;
    }
}