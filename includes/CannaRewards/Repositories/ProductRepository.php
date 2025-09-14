<?php
namespace CannaRewards\Repositories;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\Infrastructure\WordPressApiWrapper;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Product Repository
 * Handles data access for WooCommerce products.
 */
class ProductRepository {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    public function findIdBySku(string $sku): ?int {
        $product_id = $this->wp->getProductIdBySku($sku);
        return $product_id > 0 ? $product_id : null;
    }

    public function getPointsAward(int $product_id): int {
        return (int) $this->wp->getPostMeta($product_id, MetaKeys::POINTS_AWARD, true);
    }

    public function getPointsCost(int $product_id): int {
        return (int) $this->wp->getPostMeta($product_id, MetaKeys::POINTS_COST, true);
    }
    
    public function getRequiredRank(int $product_id): ?string {
        $rank = $this->wp->getPostMeta($product_id, MetaKeys::REQUIRED_RANK, true);
        return empty($rank) ? null : $rank;
    }
}