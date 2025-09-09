<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Repositories\ActionLogRepository;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Referral Service
 *
 * Handles all business logic related to referrals.
 */
class ReferralService {
    private CDPService $cdp_service;
    private UserRepository $user_repository;
    private ActionLogRepository $action_log_repository;

    public function __construct(
        CDPService $cdp_service,
        UserRepository $user_repository,
        ActionLogRepository $action_log_repository
    ) {
        $this->cdp_service = $cdp_service;
        $this->user_repository = $user_repository;
        $this->action_log_repository = $action_log_repository;
        
        Event::listen('product_scanned', [$this, 'handle_referral_conversion']);
    }

    /**
     * Processes a new user who signed up with a referral code.
     */
    public function process_new_user_referral( int $new_user_id, string $referral_code ) {
        if ( empty($new_user_id) || empty($referral_code) ) {
            return;
        }

        $referrer_user_id = $this->user_repository->findUserIdByReferralCode($referral_code);

        if ( $referrer_user_id ) {
            $this->user_repository->setReferredBy($new_user_id, $referrer_user_id);
            $this->execute_triggers('referral_invitee_signed_up', $new_user_id, ['referrer_id' => $referrer_user_id]);
        }
    }

    /**
     * Event handler that checks if a first scan is a referral conversion.
     */
    public function handle_referral_conversion( array $payload ) {
        $user_id = $payload['user_snapshot']['identity']['user_id'] ?? 0;
        if (empty($user_id)) { 
            return; 
        }

        // We check for 1 scan, because the scan has already been logged by this point.
        if ( 1 === $this->action_log_repository->countUserActions($user_id, 'scan') ) {
            $referrer_user_id = $this->user_repository->getReferringUserId($user_id);
            
            if ( $referrer_user_id ) {
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

        foreach ($triggers_to_run as $trigger_post) {
            $action_type = get_post_meta($trigger_post->ID, 'action_type', true);
            $action_value = get_post_meta($trigger_post->ID, 'action_value', true);
            
            if ($action_type === 'grant_points') {
                $points_to_grant = (int) $action_value;
                if ($points_to_grant > 0) {
                    // Instead of calling EconomyService, we broadcast an event.
                    // This fully decouples the services.
                    Event::broadcast('points_to_be_granted', [
                        'user_id'     => $user_id,
                        'points'      => $points_to_grant,
                        'description' => $trigger_post->post_title
                    ]);
                }
            }
        }

        $this->cdp_service->track($user_id, $event_key, $context);
    }
    
    /**
     * Generates a unique referral code for a new user.
     */
    public function generate_code_for_new_user( int $user_id, string $first_name = '' ): string {
        $base_code_name = ! empty( $first_name ) ? $first_name : 'USER';
        $base_code      = strtoupper( substr( preg_replace( '/[^a-zA-Z0-9]/', '', $base_code_name ), 0, 8 ) );
        do {
            $unique_part = strtoupper( wp_generate_password( 4, false, false ) );
            $new_code    = $base_code . $unique_part;
            $exists = $this->user_repository->findUserIdByReferralCode($new_code);
        } while ( ! is_null( $exists ) );
        
        $this->user_repository->saveReferralCode($user_id, $new_code);
        return $new_code;
    }

    // Placeholder for a future feature.
    public function get_user_referrals( int $user_id ): array {
        return [];
    }
    
    // Placeholder for a future feature.
    public function get_nudge_options_for_referee( int $user_id, string $referee_email ): array {
        return [];
    }
}