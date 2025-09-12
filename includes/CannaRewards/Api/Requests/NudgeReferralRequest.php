<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class NudgeReferralRequest extends FormRequest {

    protected function rules(): array {
        return [
            'email' => ['required', 'email'],
        ];
    }

    public function get_referee_email(): string {
        $validated = $this->validated();

        return $validated['email'];
    }
}