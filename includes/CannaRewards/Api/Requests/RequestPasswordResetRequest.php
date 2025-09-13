<?php
namespace CannaRewards\Api\Requests;
use CannaRewards\Api\FormRequest;

if (!defined('WPINC')) { die; }

class RequestPasswordResetRequest extends FormRequest {
    protected function rules(): array {
        return [
            'email' => ['required', 'email'],
        ];
    }
    public function getEmail(): string {
        return $this->validated()['email'];
    }
}