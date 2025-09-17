<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;

final class PhoneNumber {
    private function __construct(public readonly string $value) {}

    public static function fromString(string $number): self {
        // Basic validation: remove non-digits, check length.
        // For production, use a library like giggsey/libphonenumber-for-php
        $digits = preg_replace('/\D/', '', $number);
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException("Invalid phone number format provided.");
        }
        return new self($digits);
    }

    public function __toString(): string {
        return $this->value;
    }
}