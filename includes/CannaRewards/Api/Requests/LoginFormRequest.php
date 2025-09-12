<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class LoginFormRequest extends FormRequest {

    protected function rules(): array {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    public function get_credentials(): array {
        $validated = $this->validated();

        return [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];
    }
}