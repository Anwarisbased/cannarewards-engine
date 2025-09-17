<?php
namespace CannaRewards\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ShippingAddress",
    description: "A standard shipping address object."
)]
final class ShippingAddressDTO {
    public function __construct(
        #[OA\Property(example: "Jane")]
        public readonly string $firstName,
        #[OA\Property(example: "Doe")]
        public readonly string $lastName,
        #[OA\Property(example: "123 Main St")]
        public readonly string $address1,
        #[OA\Property(example: "Anytown")]
        public readonly string $city,
        #[OA\Property(example: "CA")]
        public readonly string $state,
        #[OA\Property(example: "90210")]
        public readonly string $postcode
    ) {}
}