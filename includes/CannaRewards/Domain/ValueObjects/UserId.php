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

    public function toInt(): int {
        return $this->value;
    }
}