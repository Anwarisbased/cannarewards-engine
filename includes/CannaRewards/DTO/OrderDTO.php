<?php
namespace CannaRewards\DTO;

// Represents a single redeemed order for API responses.
final class OrderDTO {
    public int $orderId;
    public string $date; // Will be in 'YYYY-MM-DD' format
    public string $status;
    public string $items;
    public string $imageUrl;
}