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
 * This service is now event-driven and configurable via Triggers.
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
            
            // Execute any triggers configured for this event (e.g., grant points to the new user).
            $this->execute_triggers('referral_invitee_signed_up', $user_id, ['referrer_id' => $referrer_user_id]);
        }
    }

    /**
     * Event handler that checks if a first scan is a referral conversion.
     */
    public function handle_referral_conversion( array $payload ) {
        global $wpdb;
        $user_id = $payload['user_id'] ?? 0;
        if (empty($user_id)) { return; }

        // The action log is the source of truth. A count of 1 means this is the first scan.
        $scan_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(log_id) FROM {$wpdb->prefix}canna_user_action_log WHERE user_id = %d AND action_type = 'scan'", $user_id ) );
        
        if ( 1 === $scan_count ) {
            $referrer_user_id = (int) get_user_meta( $user_id, '_canna_referred_by_user_id', true );
            
            if ( $referrer_user_id > 0 ) {
                // This is a conversion! Execute triggers for the REFERRER.
                $this->execute_triggers('referral_converted', $referrer_user_id, ['invitee_id' => $user_id]);
            }
        }
    }
    
    /**
     * Finds and executes all active Triggers for a given event key.
     *
     * @param string $event_key The event to find triggers for.
     * @param int    $user_id The ID of the user to apply the action to.
     * @param array  $context Additional context for logging.
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
            // Future actions like 'grant_product' would be added here.
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
}