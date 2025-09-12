<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class RegisterUserRequest extends FormRequest {

    protected function rules(): array {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'firstName' => ['required'],
            'agreedToTerms' => ['required'],
        ];
    }

    public function to_command(): CreateUserCommand {
        $validated = $this->validated();

        return new CreateUserCommand(
            new EmailAddress($validated['email']),
            $validated['password'],
            $validated['firstName'],
            $validated['lastName'] ?? '',
            $validated['phone'] ?? '',
            (bool) $validated['agreedToTerms'],
            (bool) ($validated['agreedToMarketing'] ?? false),
            $validated['referralCode'] ?? null
        );
    }
}