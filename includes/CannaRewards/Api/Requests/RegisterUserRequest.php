<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Domain\ValueObjects\PlainTextPassword;
use CannaRewards\Domain\ValueObjects\PhoneNumber;
use CannaRewards\Domain\ValueObjects\ReferralCode;

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
            EmailAddress::fromString($validated['email']),
            PlainTextPassword::fromString($validated['password']),
            $validated['firstName'],
            $validated['lastName'] ?? '',
            !empty($validated['phone']) ? PhoneNumber::fromString($validated['phone']) : null,
            (bool) $validated['agreedToTerms'],
            (bool) ($validated['agreedToMarketing'] ?? false),
            !empty($validated['referralCode']) ? ReferralCode::fromString($validated['referralCode']) : null
        );
    }
}