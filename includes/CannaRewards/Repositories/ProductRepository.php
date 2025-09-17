<?php
namespace CannaRewards\Repositories;

use CannaRewards\Domain\MetaKeys;
use CannaRewards\Domain\ValueObjects\ProductId;
use CannaRewards\Domain\ValueObjects\Sku;
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

    public function findIdBySku(Sku $sku): ?ProductId {
        $product_id = $this->wp->getProductIdBySku($sku->value);
        return $product_id > 0 ? ProductId::fromInt($product_id) : null;
    }

    public function getPointsAward(ProductId $product_id): int {
        return (int) $this->wp->getPostMeta($product_id->toInt(), MetaKeys::POINTS_AWARD, true);
    }

    public function getPointsCost(ProductId $product_id): int {
        return (int) $this->wp->getPostMeta($product_id->toInt(), MetaKeys::POINTS_COST, true);
    }
    
    public function getRequiredRank(ProductId $product_id): ?string {
        $rank = $this->wp->getPostMeta($product_id->toInt(), MetaKeys::REQUIRED_RANK, true);
        return empty($rank) ? null : $rank;
    }
}