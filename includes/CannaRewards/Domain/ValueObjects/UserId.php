<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

// A Value Object that guarantees a user ID is a positive integer.
final class UserId {
    private int $value;

    public function __construct(int $id) {
        if ($id <= 0) {
            throw new InvalidArgumentException("User ID must be a positive integer. Received: {$id}");
        }
        $this->value = $id;
    }

    public static function fromInt(int $id): self {
        return new self($id);
    }

    public function toInt(): int {
        return $this->value;
    }

    public function __serialize(): array {
        return ['value' => $this->value];
    }

    public function jsonSerialize(): int {
        return $this->value;
    }

    public function __toString(): string {
        return (string) $this->value;
    }
}