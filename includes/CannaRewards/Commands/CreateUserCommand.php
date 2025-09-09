<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\EmailAddress;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for creating a new user.
 * It now requires a validated EmailAddress Value Object.
 */
final class CreateUserCommand {
    public EmailAddress $email; // <-- Type-hinted to the Value Object
    public string $password;
    public string $first_name;
    public string $last_name;
    public string $phone;
    public bool $agreed_to_terms;
    public bool $agreed_to_marketing;
    public ?string $referral_code;

    public function __construct(
        EmailAddress $email, // <-- The constructor demands the Value Object
        string $password,
        string $first_name,
        string $last_name,
        string $phone,
        bool $agreed_to_terms,
        bool $agreed_to_marketing,
        ?string $referral_code
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->phone = $phone;
        $this->agreed_to_terms = $agreed_to_terms;
        $this->agreed_to_marketing = $agreed_to_marketing;
        $this->referral_code = $referral_code;
    }
}