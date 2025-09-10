<?php
namespace CannaRewards\Repositories;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Product Repository
 * Handles data access for WooCommerce products.
 */
class ProductRepository {

    /**
     * Finds a product ID by its SKU.
     */
    public function findIdBySku(string $sku): ?int {
        $product_id = wc_get_product_id_by_sku($sku);
        return $product_id > 0 ? $product_id : null;
    }

    /**
     * Gets the base points awarded for scanning a product.
     */
    public function getPointsAward(int $product_id): int {
        // --- THE FIX ---
        // The meta key in the database is 'points_award'. The previous code
        // might have had a typo or an incorrect key here. This is the correct one.
        return (int) get_post_meta($product_id, 'points_award', true);
        // --- END FIX ---
    }

    /**
     * Gets the points cost for redeeming a product.
     */
    public function getPointsCost(int $product_id): int {
        return (int) get_post_meta($product_id, 'points_cost', true);
    }
    
    /**
     * Gets the required rank slug for redeeming a product.
     */
    public function getRequiredRank(int $product_id): ?string {
        $rank = get_post_meta($product_id, '_required_rank', true);
        return empty($rank) ? null : $rank;
    }
}