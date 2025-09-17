<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Domain\ValueObjects\ProductId;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

final class RedeemRewardCommand {
    public UserId $userId;
    public ProductId $productId;
    public array $shippingDetails;

    public function __construct(UserId $userId, ProductId $productId, array $shippingDetails = []) {
        $this->userId = $userId;
        $this->productId = $productId;
        $this->shippingDetails = $shippingDetails;
    }
}