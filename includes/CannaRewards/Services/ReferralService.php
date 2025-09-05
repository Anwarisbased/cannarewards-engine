<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Referral Service
 *
 * Handles business logic related to referrals, such as code generation and processing signups.
 * This service is event-driven and configurable via Triggers.
 */
class ReferralService {

    public function __construct() {
        Event::listen('user_created', [$this, 'handle_new_user_referral']);
        Event::listen('product_scanned', [$this, 'handle_referral_conversion']);
    }

    /**
     * Event handler that checks for a referral code when a new user is created.
     */
    public function handle_new_user_referral( array $payload ) {
        $user_id       = $payload['user_id'] ?? 0;
        $referral_code = $payload['referral_code'] ?? null;

        if ( empty( $user_id ) || empty( $referral_code ) ) {
            return;
        }

        $referring_users = get_users([
            'meta_key'   => '_canna_referral_code', 'meta_value' => sanitize_text_field( $referral_code ),
            'number'     => 1, 'fields'     => 'ID',
        ]);

        if ( ! empty( $referring_users ) ) {
            $referrer_user_id = $referring_users[0];
            update_user_meta( $user_id, '_canna_referred_by_user_id', $referrer_user_id );
            
            $this->execute_triggers('referral_invitee_signed_up', $user_id, ['referrer_id' => $referrer_user_id]);
        }
    }

    /**
     * Event handler that checks if a first scan is a referral conversion.
     */
    public function handle_referral_conversion( array $payload ) {
        global $wpdb;
        $user_id = $payload['user_snapshot']['identity']['user_id'] ?? 0;
        if (empty($user_id)) { return; }

        $scan_count = $payload['user_snapshot']['engagement']['total_scans'] ?? 1;
        
        if ( 1 === $scan_count ) {
            $referrer_user_id = (int) get_user_meta( $user_id, '_canna_referred_by_user_id', true );
            
            if ( $referrer_user_id > 0 ) {
                $this->execute_triggers('referral_converted', $referrer_user_id, ['invitee_id' => $user_id]);
            }
        }
    }
    
    /**
     * Finds and executes all active Triggers for a given event key.
     */
    private function execute_triggers(string $event_key, int $user_id, array $context = []) {
        $triggers_to_run = get_posts([
            'post_type'      => 'canna_trigger',
            'posts_per_page' => -1,
            'meta_key'       => 'event_key',
            'meta_value'     => $event_key,
        ]);

        if (empty($triggers_to_run)) {
            return;
        }

        $economy_service = new EconomyService();
        $cdp_service = new CDPService();

        foreach ($triggers_to_run as $trigger_post) {
            $action_type = get_post_meta($trigger_post->ID, 'action_type', true);
            $action_value = get_post_meta($trigger_post->ID, 'action_value', true);
            
            if ($action_type === 'grant_points') {
                $points_to_grant = (int) $action_value;
                if ($points_to_grant > 0) {
                    $economy_service->grant_points($user_id, $points_to_grant, $trigger_post->post_title);
                }
            }
        }

        $cdp_service->track($user_id, $event_key, $context);
    }
    
    /**
     * Generates a unique referral code for a new user.
     */
    public function generate_code_for_new_user( int $user_id, string $first_name = '' ): string {
        global $wpdb;
        $base_code_name = ! empty( $first_name ) ? $first_name : 'USER';
        $base_code      = strtoupper( substr( preg_replace( '/[^a-zA-Z0-9]/', '', $base_code_name ), 0, 8 ) );
        do {
            $unique_part = strtoupper( wp_generate_password( 4, false, false ) );
            $new_code    = $base_code . $unique_part;
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = '_canna_referral_code' AND meta_value = %s", $new_code ) );
        } while ( ! is_null( $exists ) );
        update_user_meta( $user_id, '_canna_referral_code', $new_code );
        return $new_code;
    }

    /**
     * Retrieves a formatted list of users referred by a specific user.
     */
    public function get_user_referrals( int $referrer_id ): array {
        global $wpdb;
        $results = [];
        $invitees = get_users([
            'meta_key' => '_canna_referred_by_user_id',
            'meta_value' => $referrer_id,
            'orderby' => 'user_registered',
            'order' => 'DESC',
        ]);

        if (empty($invitees)) {
            return $results;
        }

        $scan_log_table = $wpdb->prefix . 'canna_user_action_log';

        foreach ($invitees as $invitee) {
            $first_scan_exists = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT log_id FROM {$scan_log_table} WHERE user_id = %d AND action_type = 'scan' LIMIT 1",
                $invitee->ID
            ));

            $results[] = [
                'name' => $invitee->first_name ?: $invitee->display_name,
                'email' => $invitee->user_email,
                'join_date' => date('F j, Y', strtotime($invitee->user_registered)),
                'status_key' => $first_scan_exists ? 'awarded' : 'pending',
                'status' => $first_scan_exists ? 'Awarded' : 'Pending First Scan',
            ];
        }
        return $results;
    }

    /**
     * Generates pre-composed "nudge" messages for a pending referee.
     */
    public function get_nudge_options_for_referee( int $referrer_id, string $referee_email ): array {
        $referee = get_user_by('email', $referee_email);

        if (!$referee || (int) get_user_meta($referee->ID, '_canna_referred_by_user_id', true) !== $referrer_id) {
            throw new \Exception("You do not have permission to nudge this user.");
        }
        
        return [
            'share_options' => [
                "Hey! Just a friendly reminder to complete your first scan with CannaRewards to unlock your welcome gift!",
                "Don't forget to scan a product to get your CannaRewards welcome bonus! Let me know if you have any questions.",
            ]
        ];
    }
}