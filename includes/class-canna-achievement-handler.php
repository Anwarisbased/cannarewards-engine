<?php
/**
 * Handles the core gamification logic for CannaRewards achievements.
 *
 * This class provides methods to award achievements to users and to check for
 * achievement eligibility based on various user actions (scanning, redeeming, profile updates).
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
     * Initializes the achievement handler.
     */
    public static function init() {
        // This class is called statically from other event-driven methods.
    }

    /**
     * Awards an achievement to a user if they don't already have it.
     * This is the single, authoritative method for awarding an achievement.
     *
     * @param int    $user_id         The ID of the user.
     * @param string $achievement_key The unique key of the achievement to award.
     * @return bool True if the achievement was awarded, false if already unlocked or not found/inactive.
     */
    private static function award_achievement($user_id, $achievement_key) {
        global $wpdb;
        $user_achievements_table = $wpdb->prefix . 'canna_user_achievements';
        $achievements_table      = $wpdb->prefix . 'canna_achievements';

        // --- REFINEMENT: Check if the user already has this achievement first. It's a quick check. ---
        $has_achievement = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$user_achievements_table}` WHERE user_id = %d AND achievement_key = %s LIMIT 1",
                $user_id,
                $achievement_key
            )
        );

        if ($has_achievement) {
            return false; // User already has this. Stop immediately.
        }

        // --- REFINEMENT: Get achievement details, but ONLY if it's active. ---
        // This single query confirms the achievement exists and is ready to be awarded.
        $achievement_details = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT title, points_reward FROM `{$achievements_table}` WHERE achievement_key = %s AND is_active = 1",
                $achievement_key
            )
        );

        if (!$achievement_details) {
            return false; // Achievement not found or is not active.
        }

        // Award the achievement by inserting into the user's record.
        $inserted = $wpdb->insert(
            $user_achievements_table,
            [
                'user_id'         => $user_id,
                'achievement_key' => $achievement_key,
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

    /**
     * Checks for and awards achievements when a user scans a product.
     *
     * @param int $user_id The ID of the user who scanned.
     */
    public static function check_on_scan($user_id) {
        // Award 'first_scan' achievement.
        self::award_achievement($user_id, 'first_scan');
    }

    /**
     * Checks for and awards achievements when a user redeems a reward.
     *
     * @param int $user_id The ID of the user who redeemed.
     */
    public static function check_on_redeem($user_id) {
        // Award 'first_redeem' achievement.
        self::award_achievement($user_id, 'first_redeem');
    }

    /**
     * Checks for and awards achievements when a user updates their profile.
     *
     * @param int   $user_id The ID of the user whose profile was updated.
     * @param array $data    Optional: Array of updated user data.
     */
    public static function check_on_profile_update($user_id, $data = []) {
        // Future profile-related achievement checks will go here.
    }
}