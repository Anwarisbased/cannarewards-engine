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
     * This method can be used to hook into WordPress actions if needed.
     */
    public static function init() {
        // Example: Hook into user actions if needed, though specific checks are done via static methods.
        // add_action('canna_user_scanned_product', [__CLASS__, 'check_on_scan'], 10, 1);
        // add_action('canna_user_redeemed_reward', [__CLASS__, 'check_on_redeem'], 10, 1);
        // add_action('profile_update', [__CLASS__, 'check_on_profile_update'], 10, 2);
    }

    /**
     * Awards an achievement to a user if they don't already have it.
     *
     * @param int    $user_id         The ID of the user.
     * @param string $achievement_key The unique key of the achievement to award.
     * @return bool True if the achievement was awarded, false if already unlocked or not found.
     */
    private static function award_achievement($user_id, $achievement_key) {
        global $wpdb;
        $user_achievements_table = $wpdb->prefix . 'canna_user_achievements';
        $achievements_table      = $wpdb->prefix . 'canna_achievements';

        // 1. Check if the user already has this achievement.
        $has_achievement = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$user_achievements_table}` WHERE user_id = %d AND achievement_key = %s",
                $user_id,
                $achievement_key
            )
        );

        if ($has_achievement > 0) {
            return false; // User already has this achievement.
        }

        // 2. Get achievement details (especially points_reward and is_active).
        $achievement_details = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT points_reward, is_active FROM `{$achievements_table}` WHERE achievement_key = %s",
                $achievement_key
            )
        );

        if (!$achievement_details || !$achievement_details->is_active) {
            return false; // Achievement not found or not active.
        }

        // 3. Award the achievement.
        $inserted = $wpdb->insert(
            $user_achievements_table,
            [
                'user_id'         => $user_id,
                'achievement_key' => $achievement_key,
                'unlocked_at'     => current_time('mysql'),
            ],
            [
                '%d', // user_id
                '%s', // achievement_key
                '%s', // unlocked_at
            ]
        );

        if ($inserted) {
            // 4. Award points if any.
            if ($achievement_details->points_reward > 0 && class_exists('Canna_Points_Handler')) {
                Canna_Points_Handler::add_points(
                    $user_id,
                    $achievement_details->points_reward,
                    sprintf(__('Achievement Unlocked: %s', 'canna-rewards'), $achievement_key)
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
        // Example: Award 'first_scan' achievement.
        self::award_achievement($user_id, 'first_scan');

        // Add more scan-related achievement checks here.
        // e.g., 'scanned_5_products', 'scanned_different_categories', etc.
    }

    /**
     * Checks for and awards achievements when a user redeems a reward.
     *
     * @param int $user_id The ID of the user who redeemed.
     */
    public static function check_on_redeem($user_id) {
        // Example: Award 'first_redeem' achievement.
        self::award_achievement($user_id, 'first_redeem');

        // Add more redeem-related achievement checks here.
        // e.g., 'redeemed_high_value_reward', 'redeemed_5_rewards', etc.
    }

    /**
     * Checks for and awards achievements when a user updates their profile.
     *
     * @param int   $user_id The ID of the user whose profile was updated.
     * @param array $data    Optional: Array of updated user data (e.g., for specific fields).
     */
    public static function check_on_profile_update($user_id, $data = []) {
        // Example: Award 'profile_complete' achievement if certain fields are filled.
        // This would require checking specific user meta values.
        // if (!empty($data['first_name']) && !empty($data['last_name']) && !empty($data['birthday'])) {
        //     self::award_achievement($user_id, 'profile_complete');
        // }

        // Add more profile-related achievement checks here.
        // e.g., 'added_birthday', 'uploaded_avatar', etc.
    }
}