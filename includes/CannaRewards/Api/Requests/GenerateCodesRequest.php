<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class GenerateCodesRequest extends FormRequest {

    protected function rules(): array {
        return [
            'sku' => ['required'],
            'quantity' => ['integer', 'min:1', 'max:1000'],
        ];
    }

    public function get_sku(): string {
        $validated = $this->validated();

        return $validated['sku'];
    }

    public function get_quantity(): int {
        $validated = $this->validated();

        return $validated['quantity'] ?? 10;
    }
}