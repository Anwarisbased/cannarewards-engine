<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Domain\ValueObjects\PlainTextPassword;
use CannaRewards\Domain\ValueObjects\PhoneNumber;
use CannaRewards\Domain\ValueObjects\ReferralCode;

final class RegisterWithTokenCommand {
    public EmailAddress $email;
    public PlainTextPassword $password;
    public string $first_name;
    public string $last_name;
    public ?PhoneNumber $phone;
    public bool $agreed_to_terms;
    public bool $agreed_to_marketing;
    public ?ReferralCode $referral_code;
    public string $registration_token;

    public function __construct(
        EmailAddress $email,
        PlainTextPassword $password,
        string $first_name,
        string $last_name,
        ?PhoneNumber $phone,
        bool $agreed_to_terms,
        bool $agreed_to_marketing,
        ?ReferralCode $referral_code,
        string $registration_token
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->phone = $phone;
        $this->agreed_to_terms = $agreed_to_terms;
        $this->agreed_to_marketing = $agreed_to_marketing;
        $this->referral_code = $referral_code;
        $this->registration_token = $registration_token;
    }
}