<?php
/**
 * Handles Referral API Endpoints
 *
 * @package CannaRewards
 */

if (!defined('WPINC')) { die; }

class Canna_Referral_Controller {

    /**
     * Registers all referral-related REST API routes.
     */
    public static function register_routes() {
        $base = 'rewards/v1';
        $permission_loggedin = function () { return is_user_logged_in(); };

        register_rest_route($base, '/me/referrals',     ['methods' => 'GET',  'callback' => [__CLASS__, 'get_my_referrals'], 'permission_callback' => $permission_loggedin]);
        register_rest_route($base, '/me/referrals/nudge', ['methods' => 'POST', 'callback' => [__CLASS__, 'send_nudge'], 'permission_callback' => $permission_loggedin]);
    }

    public static function get_my_referrals(WP_REST_Request $request) {
        $current_user_id = get_current_user_id();
        $args = [
            'meta_key'   => '_canna_referred_by_user_id',
            'meta_value' => $current_user_id,
            'fields'     => ['ID', 'display_name', 'user_registered', 'user_email'],
        ];
        $referred_users = get_users($args);
        $response_data = [];
        foreach ($referred_users as $user) {
            $has_scanned = get_user_meta($user->ID, '_has_completed_first_scan', true);
            $response_data[] = [
                'name'         => $user->display_name,
                'email'        => $user->user_email,
                'join_date'    => date('F j, Y', strtotime($user->user_registered)),
                'status'       => $has_scanned ? 'Bonus Awarded' : 'Pending First Scan',
                'status_key'   => $has_scanned ? 'awarded' : 'pending',
            ];
        }
        return new WP_REST_Response($response_data, 200);
    }

    public static function send_nudge(WP_REST_Request $request) {
        $referrer = wp_get_current_user();
        $params = $request->get_json_params();
        $referee_email = sanitize_email($params['email'] ?? '');
        if (empty($referee_email)) return new WP_Error('bad_request', 'Referee email is required.', ['status' => 400]);
        $referee = get_user_by('email', $referee_email);
        if (!$referee || (int) get_user_meta($referee->ID, '_canna_referred_by_user_id', true) !== $referrer->ID) {
            return new WP_Error('forbidden', 'You are not authorized to nudge this user.', ['status' => 403]);
        }
        $transient_key = 'canna_nudge_limit_' . $referrer->ID . '_' . $referee->ID;
        if (get_transient($transient_key)) {
            return new WP_Error('too_many_requests', 'You can only send one reminder every 24 hours.', ['status' => 429]);
        }
        $options = get_option('canna_rewards_options', []);
        $base_url = !empty($options['frontend_url']) ? rtrim($options['frontend_url'], '/') : 'https://cannarewards-pwa.vercel.app';
        $scan_link = "{$base_url}/scan";
        $referee_name = $referee->first_name ? ' ' . $referee->first_name : '';
        $referrer_name = $referrer->first_name ?: 'Your friend';
        
        // --- MODIFIED: Return an array of message options ---
        $share_options = [
            "Friendly reminder from {$referrer_name} to scan your first product. Your welcome gift is waiting! Scan here: {$scan_link}",
            "You're one scan away from unlocking your welcome gift and earning points. Scan your first product to claim it: {$scan_link}",
            "Just a quick nudge from {$referrer_name}! Your CannaRewards welcome gift is ready to be claimed after your first scan: {$scan_link}"
        ];
        
        set_transient($transient_key, true, DAY_IN_SECONDS);
        return new WP_REST_Response([
            'success' => true, 
            'message' => 'Nudge options ready to be shared.', 
            'share_options' => $share_options
        ], 200);
    }
}