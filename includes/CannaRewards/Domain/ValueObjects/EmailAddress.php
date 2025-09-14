<?php
namespace CannaRewards\Domain\ValueObjects;

use CannaRewards\Infrastructure\WordPressApiWrapper;
use InvalidArgumentException;

// A Value Object that guarantees it holds a validly formatted email string.
final class EmailAddress {
    private string $value;
    private ?WordPressApiWrapper $wp;

    public function __construct(string $email, ?WordPressApiWrapper $wp = null) {
        $this->wp = $wp;
        
        // REFACTOR: Use WordPressApiWrapper if available, otherwise fall back to direct function
        if ($this->wp) {
            if (!$this->wp->isEmail($email)) {
                throw new InvalidArgumentException("Invalid email address provided.");
            }
        } else {
            // Fallback for backward compatibility
            if (!is_email($email)) {
                throw new InvalidArgumentException("Invalid email address provided.");
            }
        }
        $this->value = strtolower(trim($email));
    }

    public function __toString(): string {
        return $this->value;
    }
}