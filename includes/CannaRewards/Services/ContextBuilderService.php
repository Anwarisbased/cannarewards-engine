<?php
namespace CannaRewards\Services;

use WP_Post;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Context Builder Service
 *
 * Assembles the rich, standardized data objects (user_snapshot, product_snapshot, event_context)
 * that are attached to every event in the system. This is the heart of the data enrichment pipeline.
 */
class ContextBuilderService {

    /**
     * Builds the complete, enriched context for a given event.
     *
     * @param int     $user_id       The ID of the user the event pertains to.
     * @param ?WP_Post $product_post (Optional) The product post object if the event is product-related.
     * @return array The complete context array.
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

        // In a full implementation, we would add the calculated properties here.
        // For now, we'll build the core structure.
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
            ],
            // ... other snapshot categories from the taxonomy will be added here.
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

        // In a full implementation, we would pull all attributes and tags here.
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
        // In a production environment, use a trusted GeoIP library.
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