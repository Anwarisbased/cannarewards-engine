<?php
namespace CannaRewards\Commands;

final class RegisterWithTokenCommand {
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $phone;
    public $agreed_to_terms;
    public $agreed_to_marketing;
    public $referral_code;
    public $registration_token;

    public function __construct(
        string $email,
        string $password,
        string $first_name,
        string $last_name,
        string $phone,
        bool $agreed_to_terms,
        bool $agreed_to_marketing,
        ?string $referral_code,
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