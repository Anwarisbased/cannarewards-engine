<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

final class OrderId {
    private function __construct(public readonly int $value) {}

    public static function fromInt(int $id): self {
        if ($id <= 0) {
            throw new InvalidArgumentException("Order ID must be a positive integer. Received: {$id}");
        }
        return new self($id);
    }

    public function toInt(): int {
        return $this->value;
    }
    
    public function __toString(): string {
        return (string) $this->value;
    }
}