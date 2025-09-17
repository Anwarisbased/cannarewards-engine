<?php
namespace CannaRewards\DTO;

use CannaRewards\Domain\ValueObjects\OrderId;
use OpenApi\Attributes as OA;
use JsonSerializable;

#[OA\Schema(
    schema: "Order",
    description: "Represents a single redeemed order."
)]
final class OrderDTO implements JsonSerializable {
    public function __construct(
        #[OA\Property]
        public readonly OrderId $orderId,
        #[OA\Property(format: "date")]
        public readonly string $date,
        #[OA\Property]
        public readonly string $status,
        #[OA\Property]
        public readonly string $items,
        #[OA\Property(format: "uri")]
        public readonly string $imageUrl
    ) {}
    
    public function jsonSerialize(): array {
        return [
            'orderId' => $this->orderId->toInt(),
            'date' => $this->date,
            'status' => $this->status,
            'items' => $this->items,
            'imageUrl' => $this->imageUrl
        ];
    }
}