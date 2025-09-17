<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Domain\ValueObjects\PlainTextPassword;
use CannaRewards\Infrastructure\WordPressApiWrapper;

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

        // REFACTOR: Get WordPressApiWrapper from the global container to pass to EmailAddress
        $wp = \CannaRewards()->get(WordPressApiWrapper::class);

        return new RegisterWithTokenCommand(
            EmailAddress::fromString($validated['email'], $wp),
            PlainTextPassword::fromString($validated['password']),
            $validated['firstName'],
            $validated['lastName'] ?? '',
            !empty($validated['phone']) ? \CannaRewards\Domain\ValueObjects\PhoneNumber::fromString($validated['phone']) : null,
            (bool) $validated['agreedToTerms'],
            (bool) ($validated['agreedToMarketing'] ?? false),
            !empty($validated['referralCode']) ? \CannaRewards\Domain\ValueObjects\ReferralCode::fromString($validated['referralCode']) : null,
            $validated['registration_token']
        );
    }
}