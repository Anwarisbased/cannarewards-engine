<?php
/**
 * The core processing engine for dynamic achievements.
 *
 * This class evaluates user actions against a set of rules defined in the
 * Achievement CPT to determine if a user should unlock an achievement.
 *
 * @package CannaRewards
 * @subpackage Gamification
 */

// Exit if accessed directly.
if (!defined('WPINC')) {
    die;
}

class Canna_Achievement_Engine {

    /**
     * Main entry point for the engine. Processes an event for a user.
     *
     * @param string $event_type The type of event (e.g., 'scan', 'redeem').
     * @param int    $user_id    The user who triggered the event.
     * @param array  $context    Additional data about the event (e.g., product_id).
     */
    public static function process_event($event_type, $user_id, $context = []) {
        global $wpdb;
        $achievements_table = $wpdb->prefix . 'canna_achievements';
        $user_achievements_table = $wpdb->prefix . 'canna_user_achievements';

        // 1. Get all active achievements for this event trigger.
        $achievements_to_check = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$achievements_table} WHERE trigger_type = %s AND is_active = 1",
            $event_type
        ));

        if (empty($achievements_to_check)) {
            return;
        }

        // 2. Filter out achievements the user has already unlocked.
        $unlocked_keys_raw = $wpdb->get_col($wpdb->prepare("SELECT achievement_key FROM {$user_achievements_table} WHERE user_id = %d", $user_id));
        $unlocked_keys = array_flip($unlocked_keys_raw);

        $candidate_achievements = array_filter($achievements_to_check, function($ach) use ($unlocked_keys) {
            return !isset($unlocked_keys[$ach->achievement_key]);
        });

        if (empty($candidate_achievements)) {
            return;
        }

        // 3. Loop through candidates and evaluate their rules.
        foreach ($candidate_achievements as $achievement) {
            $rules_met = self::evaluate_achievement_rules($user_id, $achievement->achievement_key, $context);
            if ($rules_met) {
                Canna_Achievement_Handler::award_achievement($user_id, $achievement);
            }
        }
    }

    /**
     * Evaluates all rule groups for a specific achievement.
     *
     * @param int    $user_id         The user ID.
     * @param string $achievement_key The achievement to check.
     * @param array  $context         Event context data.
     * @return bool True if any rule group's conditions are met.
     */
    private static function evaluate_achievement_rules($user_id, $achievement_key, $context) {
        $rule_groups = get_post_meta(get_page_by_path($achievement_key, OBJECT, 'canna_achievement')->ID, 'rule_groups', true);

        if (empty($rule_groups) || !is_array($rule_groups)) {
            return true; // No rules defined, award by default.
        }

        // OR logic between groups
        foreach ($rule_groups as $group) {
            if (self::evaluate_condition_group($user_id, $group, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluates a single group of conditions.
     *
     * @param int   $user_id   The user ID.
     * @param array $group     An array of conditions.
     * @param array $context   Event context data.
     * @return bool True only if ALL conditions in the group are met.
     */
    private static function evaluate_condition_group($user_id, $group, $context) {
        if (empty($group) || !is_array($group)) {
            return false;
        }
        
        // AND logic within a group
        foreach ($group as $condition) {
            if (!self::evaluate_condition($user_id, $condition, $context)) {
                return false; // If any condition fails, the whole group fails.
            }
        }

        return true; // All conditions in the group passed.
    }

    /**
     * Evaluates a single condition. THIS IS THE CORE LOGIC & BUG FIX.
     *
     * @param int   $user_id   The user ID.
     * @param array $condition The condition data (subject, operator, value).
     * @param array $context   Event context data.
     * @return bool Whether the condition is met.
     */
    private static function evaluate_condition($user_id, $condition, $context) {
        global $wpdb;
        $subject = $condition['subject'];
        $operator = $condition['operator'];
        $required_value = $condition['value'];

        $actual_value = null;

        // Data Fetching based on Subject
        switch ($subject) {
            case 'log_total_scans':
                $table = $wpdb->prefix . 'canna_user_action_log';
                $actual_value = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(log_id) FROM {$table} WHERE user_id = %d AND action_type = 'scan'",
                    $user_id
                ));
                break;
            // ... other cases for different subjects (e.g., 'user_rank', 'product_attribute_strain') will be added here.
        }

        if ($actual_value === null) return false;

        // Comparison Logic
        switch ($operator) {
            case '==': return $actual_value == $required_value;
            case '!=': return $actual_value != $required_value;
            case '>=': return $actual_value >= $required_value;
            case '<=': return $actual_value <= $required_value;
            case '>':  return $actual_value > $required_value;
            case '<':  return $actual_value < $required_value;
            default: return false;
        }
    }

    /**
     * Logs a user action to the database.
     *
     * @param int    $user_id     The user who performed the action.
     * @param string $action_type The type of action (e.g., 'scan').
     * @param array  $context     Contextual data about the action.
     */
    public static function log_action($user_id, $action_type, $context = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'canna_user_action_log';
        
        $meta_data = [];
        if ($action_type === 'scan' && isset($context['product_id'])) {
            $product = wc_get_product($context['product_id']);
            if ($product) {
                foreach ($product->get_attributes() as $attribute) {
                    $meta_data[$attribute->get_name()] = $product->get_attribute($attribute->get_name());
                }
            }
        }

        $wpdb->insert($table, [
            'user_id'     => $user_id,
            'action_type' => $action_type,
            'object_id'   => $context['product_id'] ?? null,
            'meta_data'   => json_encode($meta_data),
            'created_at'  => current_time('mysql'),
        ]);
    }
}