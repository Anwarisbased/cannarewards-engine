<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

final class Sku {
    private function __construct(public readonly string $value) {}

    public static function fromString(string $sku): self {
        $trimmedSku = trim($sku);
        if (empty($trimmedSku)) {
            throw new InvalidArgumentException("SKU cannot be empty.");
        }
        return new self($trimmedSku);
    }

    public function __toString(): string {
        return $this->value;
    }
}