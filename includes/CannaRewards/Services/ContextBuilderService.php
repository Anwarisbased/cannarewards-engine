<?php
namespace CannaRewards\Services;

use WP_Post;
use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Repositories\ActionLogRepository; // <<<--- IMPORT THE REPOSITORY
use CannaRewards\Infrastructure\WordPressApiWrapper; // <<<--- IMPORT THE WRAPPER

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Context Builder Service
 */
class ContextBuilderService {

    private RankService $rankService;
    private ActionLogRepository $actionLogRepo; // <<<--- ADD THE REPOSITORY PROPERTY
    private WordPressApiWrapper $wp; // <<<--- ADD THE WRAPPER PROPERTY

    public function __construct(
        RankService $rankService,
        ActionLogRepository $actionLogRepo, // <<<--- INJECT THE REPOSITORY
        WordPressApiWrapper $wp // <<<--- INJECT THE WRAPPER
    ) {
        $this->rankService = $rankService;
        $this->actionLogRepo = $actionLogRepo;
        $this->wp = $wp;
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
        $user = $this->wp->getUserById($user_id);
        if ( ! $user ) {
            return [];
        }

        // --- THIS IS THE FIX ---
        // Instead of a direct DB query, we use the clean, abstracted repository method.
        $total_scans = $this->actionLogRepo->countUserActions($user_id, 'scan');
        // --- END FIX ---
        
        $userIdVO = UserId::fromInt($user_id);
        $rank_dto = $this->rankService->getUserRank($userIdVO);

        return [
            'identity' => [
                'user_id'    => $user_id,
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'created_at' => $user->user_registered . 'Z',
            ],
            'economy'  => [
                // Also fixing these to use the wrapper for consistency
                'points_balance' => (int) $this->wp->getUserMeta($user_id, '_canna_points_balance', true),
                'lifetime_points' => (int) $this->wp->getUserMeta($user_id, '_canna_lifetime_points', true),
            ],
            'status' => [
                'rank_key' => (string) $rank_dto->key,
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
        $product = $this->wp->getProduct($product_post->ID);
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