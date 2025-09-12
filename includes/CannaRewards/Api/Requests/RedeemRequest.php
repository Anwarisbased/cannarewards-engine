<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\RedeemRewardCommand;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class RedeemRequest extends FormRequest {

    protected function rules(): array {
        return [
            'productId' => ['required', 'integer'],
            'shippingDetails' => ['array'],
            'shippingDetails.first_name' => ['required'],
            'shippingDetails.last_name' => ['required'],
            'shippingDetails.address_1' => ['required'],
            'shippingDetails.city' => ['required'],
            'shippingDetails.state' => ['required'],
            'shippingDetails.postcode' => ['required'],
        ];
    }

    public function to_command(int $user_id): RedeemRewardCommand {
        $validated = $this->validated();

        return new RedeemRewardCommand(
            $user_id,
            (int) $validated['productId'],
            $validated['shippingDetails'] ?? []
        );
    }
}