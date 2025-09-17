<?php
namespace CannaRewards\Domain\ValueObjects;

final class ShippingAddress {
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $address1,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postcode
    ) {
        // Basic non-empty validation for required fields
        if (empty($firstName) || empty($lastName) || empty($address1) || empty($city) || empty($state) || empty($postcode)) {
            throw new \InvalidArgumentException("All shipping address fields are required.");
        }
    }
}