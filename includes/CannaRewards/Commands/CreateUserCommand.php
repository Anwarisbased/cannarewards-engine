<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Domain\ValueObjects\PlainTextPassword;
use CannaRewards\Domain\ValueObjects\PhoneNumber;
use CannaRewards\Domain\ValueObjects\ReferralCode;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for creating a new user.
 * It now requires a validated EmailAddress Value Object.
 */
final class CreateUserCommand {
    public EmailAddress $email;
    public PlainTextPassword $password;
    public string $firstName;
    public string $lastName;
    public ?PhoneNumber $phone;
    public bool $agreedToTerms;
    public bool $agreedToMarketing;
    public ?ReferralCode $referralCode;

    public function __construct(
        EmailAddress $email,
        PlainTextPassword $password,
        string $firstName,
        string $lastName,
        ?PhoneNumber $phone,
        bool $agreedToTerms,
        bool $agreedToMarketing,
        ?ReferralCode $referralCode
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->agreedToTerms = $agreedToTerms;
        $this->agreedToMarketing = $agreedToMarketing;
        $this->referralCode = $referralCode;
    }
}