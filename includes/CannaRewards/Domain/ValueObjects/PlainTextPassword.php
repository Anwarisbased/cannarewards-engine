<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

final class PlainTextPassword {
    private function __construct(public readonly string $value) {}

    public static function fromString(string $password): self {
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException("Password must be at least 8 characters long.");
        }
        // You could add more complexity rules here
        return new self($password);
    }

    public function getValue(): string {
        return $this->value;
    }

    public function __toString(): string {
        // Avoid accidentally logging the raw password
        return '********';
    }
}