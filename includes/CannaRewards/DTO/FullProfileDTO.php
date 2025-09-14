<?php
namespace CannaRewards\DTO;

// Represents the complete user profile data for the /users/me/profile endpoint.
final class FullProfileDTO {
    public function __construct(
        public readonly ?string $lastName,
        public readonly ?string $phone_number,
        public readonly ?string $referral_code,
        public readonly ShippingAddressDTO $shipping_address,
        public readonly array $unlocked_achievement_keys = [],
        public readonly object $custom_fields
    ) {}
}