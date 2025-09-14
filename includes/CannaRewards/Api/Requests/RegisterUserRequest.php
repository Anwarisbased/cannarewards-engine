<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Infrastructure\WordPressApiWrapper;

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

        // REFACTOR: Get WordPressApiWrapper from the global container to pass to EmailAddress
        $wp = \CannaRewards()->get(WordPressApiWrapper::class);

        return new CreateUserCommand(
            EmailAddress::fromString($validated['email'], $wp),
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