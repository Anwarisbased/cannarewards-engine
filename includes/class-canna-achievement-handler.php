<?php
/**
 * Handles the core business logic for the gamification engine.
 *
 * This class is responsible for awarding achievements, checking trigger conditions,
 * and integrating with the points handler.
 *
 * @package CannaRewards
 * @since 6.0.0
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Achievement_Handler {

    /**
     * The single instance of the class.
     * @var Canna_Achievement_Handler|null
     */
    private static $instance = null;

    /**
     * Ensures only one instance of the class is loaded.
     * @return Canna_Achievement_Handler
     */
    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // You could add hooks here if needed in the future
    }

    /**
     * Awards an achievement to a user if they haven't already unlocked it.
     *
     * This is the core private method that handles the logic of checking,
     * awarding points, and recording the achievement.
     *
     * @param int    $user_id         The ID of the user.
     * @param string $achievement_key The unique key of the achievement.
     * @return bool True if the achievement was newly awarded, false otherwise.
     */
    private function award_achievement($user_id, $achievement_key) {
        global $wpdb;
        $user_achievements_table = $wpdb->prefix . 'canna_user_achievements';
        $achievements_table = $wpdb->prefix . 'canna_achievements';

        // 1. Check if the achievement is valid and active
        $achievement = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $achievements_table WHERE achievement_key = %s AND is_active = 1",
            $achievement_key
        ));

        if (!$achievement) {
            return false; // Achievement doesn't exist or is inactive
        }

        // 2. Check if the user already has this achievement
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $user_achievements_table WHERE user_id = %d AND achievement_key = %s",
            $user_id,
            $achievement_key
        ));

        if ($existing) {
            return false; // Already unlocked
        }

        // 3. Award the achievement
        $wpdb->insert(
            $user_achievements_table,
            [
                'user_id'         => $user_id,
                'achievement_key' => $achievement_key,
                'unlocked_at'     => current_time('mysql', 1),
            ],
            ['%d', '%s', '%s']
        );

        // 4. Award points, if any
        if ($achievement->points_reward > 0) {
            if (class_exists('Canna_Points_Handler')) {
                Canna_Points_Handler::add_user_points(
                    $user_id,
                    $achievement->points_reward,
                    sprintf(__('Achievement Unlocked: %s', 'canna-rewards'), $achievement->title)
                );
            }
        }
        
        // 5. Fire an action for other integrations (like CDP)
        do_action('canna_achievement_unlocked', $user_id, $achievement_key, $achievement);

        return true;
    }

    /**
     * Trigger check: Called after a successful scan.
     *
     * @param int $user_id The user who performed the scan.
     */
    public static function check_on_scan($user_id) {
        $instance = self::init();
        // Award the 'first_scan' achievement
        $instance->award_achievement($user_id, 'first_scan');
        
        // Future: Add logic for scan streaks, etc.
    }

    /**
     * Trigger check: Called after a successful redemption.
     *
     * @param int $user_id The user who redeemed a reward.
     */
    public static function check_on_redeem($user_id) {
        $instance = self::init();
        // Award the 'first_redemption' achievement
        $instance->award_achievement($user_id, 'first_redemption');
    }

    /**
     * Trigger check: Called after a user updates their profile.
     *
     * @param int   $user_id The user who updated their profile.
     * @param array $data    The data that was updated.
     */
    public static function check_on_profile_update($user_id, $data) {
        $instance = self::init();
        // Check if a birthday was added
        if (!empty($data['birthday'])) {
            $instance->award_achievement($user_id, 'birthday_added');
        }
    }
}