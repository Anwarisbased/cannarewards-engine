<?php
namespace CannaRewards\Domain\ValueObjects;

use CannaRewards\Infrastructure\WordPressApiWrapper;
use InvalidArgumentException;

// A Value Object that guarantees it holds a validly formatted email string.
final class EmailAddress {
    private function __construct(public readonly string $value) {} // private constructor with promoted property

    public static function fromString(string $email, ?WordPressApiWrapper $wp = null): self {
        // REFACTOR: Use WordPressApiWrapper if available, otherwise fall back to direct function
        if ($wp) {
            if (!$wp->isEmail($email)) {
                throw new InvalidArgumentException("Invalid email address provided.");
            }
        } else {
            // Fallback for backward compatibility
            if (!is_email($email)) {
                throw new InvalidArgumentException("Invalid email address provided.");
            }
        }
        return new self(strtolower(trim($email)));
    }

    public function __toString(): string {
        return $this->value;
    }
}