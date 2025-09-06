<?php
namespace CannaRewards\Commands;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

final class RedeemRewardCommand {
    public $user_id;
    public $product_id;
    public $shipping_details;

    public function __construct(int $user_id, int $product_id, array $shipping_details = []) {
        $this->user_id = $user_id;
        $this->product_id = $product_id;
        $this->shipping_details = $shipping_details;
    }
}