<?php
namespace CannaRewards\DTO;

// Represents a user's shipping address for API responses.
final class ShippingAddressDTO {
    public ?string $first_name;
    public ?string $last_name;
    public ?string $address_1;
    public ?string $city;
    public ?string $state;
    public ?string $postcode;
}