<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

final class ReferralCode {
    private function __construct(public readonly string $value) {}

    public static function fromString(string $code): self {
        $trimmedCode = trim($code);
        if (empty($trimmedCode)) {
            throw new InvalidArgumentException("Referral code cannot be empty.");
        }
        return new self($trimmedCode);
    }

    public function __toString(): string {
        return $this->value;
    }
}