<?php
namespace CannaRewards\DTO;

// Represents the complete user profile data for the /users/me/profile endpoint.
final class FullProfileDTO {
    public ?string $lastName;
    public ?string $phone_number;
    public ?string $referral_code;
    public ShippingAddressDTO $shipping_address;
    public array $unlocked_achievement_keys = [];
    public object $custom_fields;
}