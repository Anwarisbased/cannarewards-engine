<?php
namespace CannaRewards\DTO;

// Represents a single redeemed order for API responses.
final class OrderDTO {
    public function __construct(
        public readonly int $orderId,
        public readonly string $date, // Will be in 'YYYY-MM-DD' format
        public readonly string $status,
        public readonly string $items,
        public readonly string $imageUrl
    ) {}
}