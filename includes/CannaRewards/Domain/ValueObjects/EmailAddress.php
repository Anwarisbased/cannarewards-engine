<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

// A Value Object that guarantees it holds a validly formatted email string.
final class EmailAddress {
    private string $value;

    public function __construct(string $email) {
        if (!is_email($email)) {
            throw new InvalidArgumentException("Invalid email address provided.");
        }
        $this->value = strtolower(trim($email));
    }

    public function __toString(): string {
        return $this->value;
    }
}