<?php
namespace CannaRewards\Services;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * CDP Service
 *
 * The single, centralized gateway for all communication to the Customer Data Platform.
 */
class CDPService {
    /**
     * The single entry point for tracking all events.
     */
    public function track( int $user_id, string $event_name, array $properties = [] ) {
        $user_snapshot = $this->build_user_snapshot( $user_id );
        $final_payload = array_merge( $properties, [ 'user_snapshot' => $user_snapshot ] );

        error_log( '[CDP TRACK]: ' . $event_name . ' | ' . wp_json_encode( $final_payload ) );
    }

    /**
     * Builds the rich user snapshot object that is attached to every event.
     */
    private function build_user_snapshot( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'identity' => [
                'user_id'    => $user_id,
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'created_at' => $user->user_registered . 'Z',
            ],
            'economy'  => [
                'points_balance' => get_user_points_balance( $user_id ),
                'lifetime_points' => get_user_lifetime_points( $user_id ),
            ],
            'status' => [
                'rank_key' => get_user_current_rank( $user_id )['key'] ?? 'member',
                'rank_name' => get_user_current_rank( $user_id )['name'] ?? 'Member',
            ]
        ];
    }
}