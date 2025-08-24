<?php
/**
 * Canna Points Handler
 *
 * This class encapsulates all business logic related to modifying user points balances,
 * handling lifetime points, and logging transactions. By centralizing these actions,
 * we ensure that point modifications are consistent and always logged correctly.
 *
 * @package CannaRewards
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Points_Handler {

    /**
     * Initializes the class.
     *
     * This method is reserved for adding any necessary WordPress hooks (actions or filters)
     * that the class might need to listen for. Currently, this class is used as a static utility
     * and does not require any hooks.
     *
     * @since 5.0.0
     */
    public static function init() {
        // No hooks are needed for this utility class at the moment.
    }

    /**
     * Adds or subtracts points from a user's balance and logs the transaction.
     *
     * This is the single, authoritative method for changing a user's points. It handles
     * updating the current balance, updating lifetime points (only for positive additions),
     * and ensuring a transaction log is always created.
     *
     * @since 5.0.0
     *
     * @param int    $user_id       The WordPress user ID.
     * @param int    $points_to_add The number of points to add. Use a negative integer to subtract points.
     * @param string $description   A brief, user-facing description of the transaction for the log.
     * @return int                 The user's new points balance after the transaction.
     */
    public static function add_user_points($user_id, $points_to_add, $description = '') {
        // Ensure inputs are of the correct type.
        $user_id       = (int) $user_id;
        $points_to_add = (int) $points_to_add;

        // Get the user's current balance. Assumes get_user_points_balance() is available.
        $current_balance = get_user_points_balance($user_id);

        $new_balance = $current_balance + $points_to_add;

        // Update the user's current point balance in their metadata.
        update_user_meta($user_id, '_canna_points_balance', $new_balance);

        // --- Lifetime Points Logic ---
        // Only update lifetime points if points are being *added*.
        // Spending points (negative value) should not decrease their lifetime total/rank.
        if ($points_to_add > 0) {
            $lifetime_points     = get_user_lifetime_points($user_id);
            $new_lifetime_points = $lifetime_points + $points_to_add;
            update_user_meta($user_id, '_canna_lifetime_points', $new_lifetime_points);
        }

        // Always log the transaction for auditing purposes.
        self::log_transaction($user_id, $points_to_add, $description);

        return $new_balance;
    }

    /**
     * Logs a points transaction to the custom `canna_points_log` database table.
     *
     * This method is private to ensure that logs are only created through the `add_user_points`
     * method, maintaining data integrity.
     *
     * @since 5.0.0
     * @access private
     *
     * @param int    $user_id     The WordPress user ID.
     * @param int    $points      The number of points in this specific transaction (can be negative).
     * @param string $description A description of the transaction.
     */
    private static function log_transaction($user_id, $points, $description) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'canna_points_log';

        $wpdb->insert(
            $table_name,
            [
                'user_id'     => $user_id,
                'points'      => $points,
                'description' => $description,
                'log_date'    => current_time('mysql', 1), // Use WordPress's timezone-aware current time.
            ],
            [
                '%d', // user_id is an integer
                '%d', // points is an integer
                '%s', // description is a string
                '%s', // log_date is a string (datetime format)
            ]
        );
    }
}