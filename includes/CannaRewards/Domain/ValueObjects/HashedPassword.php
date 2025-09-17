<?php
namespace CannaRewards\Domain\ValueObjects;

final class HashedPassword {
    private function __construct(public readonly string $value) {}

    public static function fromPlainText(PlainTextPassword $password): self {
        $hashed = wp_hash_password($password->value);
        return new self($hashed);
    }

    public static function fromHash(string $hash): self {
        // For retrieving from the database
        return new self($hash);
    }

    public function __toString(): string {
        return $this->value;
    }
}