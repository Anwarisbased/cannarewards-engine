<?php
namespace CannaRewards\Services;

use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Context Builder Service
 */
class ContextBuilderService {

    private RankService $rankService;

    public function __construct(RankService $rankService) {
        $this->rankService = $rankService;
    }

    /**
     * Builds the complete, enriched context for a given event.
     */
    public function build_event_context( int $user_id, ?WP_Post $product_post = null ): array {
        return [
            'user_snapshot'    => $this->build_user_snapshot( $user_id ),
            'product_snapshot' => $product_post ? $this->build_product_snapshot( $product_post ) : null,
            'event_context'    => $this->build_event_context_snapshot(),
        ];
    }

    /**
     * Assembles the complete user_snapshot object according to the Data Taxonomy.
     */
    private function build_user_snapshot( int $user_id ): array {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        global $wpdb;
        $log_table = $wpdb->prefix . 'canna_user_action_log';
        $total_scans = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(log_id) FROM {$log_table} WHERE user_id = %d AND action_type = 'scan'",
            $user_id
        ));
        
        // --- THIS IS THE FIX ---
        // We now use the injected RankService instead of the dead global function.
        $rank_dto = $this->rankService->getUserRank($user_id);

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
                'rank_key' => $rank_dto->key,
                'rank_name' => $rank_dto->name,
            ],
            'engagement' => [
                'total_scans' => $total_scans
            ]
        ];
    }

    /**
     * Assembles the complete product_snapshot object from a post object.
     */
    private function build_product_snapshot( WP_Post $product_post ): array {
        $product = wc_get_product( $product_post->ID );
        if ( ! $product ) {
            return [];
        }

        return [
            'identity' => [
                'product_id'   => $product->get_id(),
                'sku'          => $product->get_sku(),
                'product_name' => $product->get_name(),
            ],
            'economy' => [
                'points_award' => (int) $product->get_meta('points_award'),
                'points_cost'  => (int) $product->get_meta('points_cost'),
            ],
            'taxonomy' => [
                'product_form' => 'Vape', // Placeholder
                'strain_type'  => 'Sativa', // Placeholder
            ],
        ];
    }

    /**
     * Assembles the event_context snapshot from server variables.
     */
    private function build_event_context_snapshot(): array {
        return [
            'time'     => [
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            'location' => [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ],
            'device'   => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ],
        ];
    }
}