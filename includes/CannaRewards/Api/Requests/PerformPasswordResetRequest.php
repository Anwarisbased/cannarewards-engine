<?php
namespace CannaRewards\Api\Requests;
use CannaRewards\Api\FormRequest;

if (!defined('WPINC')) { die; }

class PerformPasswordResetRequest extends FormRequest {
    protected function rules(): array {
        return [
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ];
    }
    public function getResetData(): array {
        return $this->validated();
    }
}