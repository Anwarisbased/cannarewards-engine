<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final class Points implements JsonSerializable {
    private function __construct(public readonly int $value) {}

    public static function fromInt(int $amount): self {
        if ($amount < 0) {
            throw new InvalidArgumentException("Points cannot be negative. Received: {$amount}");
        }
        return new self($amount);
    }

    public function toInt(): int {
        return $this->value;
    }

    public function __toString(): string {
        return (string)$this->value;
    }

    public function jsonSerialize(): int {
        return $this->value;
    }
}