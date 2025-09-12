<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class RegisterWithTokenRequest extends FormRequest {

    protected function rules(): array {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'firstName' => ['required'],
            'agreedToTerms' => ['required', 'accepted'],
            'registration_token' => ['required'],
        ];
    }

    public function to_command(): RegisterWithTokenCommand {
        $validated = $this->validated();

        return new RegisterWithTokenCommand(
            new EmailAddress($validated['email']),
            $validated['password'],
            $validated['firstName'],
            $validated['lastName'] ?? '',
            $validated['phone'] ?? '',
            (bool) $validated['agreedToTerms'],
            (bool) ($validated['agreedToMarketing'] ?? false),
            $validated['referralCode'] ?? null,
            $validated['registration_token']
        );
    }
}