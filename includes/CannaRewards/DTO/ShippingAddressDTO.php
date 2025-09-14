<?php
namespace CannaRewards\DTO;

// Represents a user's shipping address for API responses.
final class ShippingAddressDTO {
    public function __construct(
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $address_1,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postcode
    ) {}
}