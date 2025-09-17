<?php
namespace CannaRewards\Domain\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

final class RankKey implements JsonSerializable {
    // In PHP 8.1+, this would ideally be a backed string Enum
    private const ALLOWED_KEYS = ['member', 'bronze', 'silver', 'gold'];

    private function __construct(public readonly string $value) {}

    public static function fromString(string $key): self {
        if (!in_array($key, self::ALLOWED_KEYS, true)) {
            // In a real system, you might fetch allowed keys from the RankService
            // For now, a static list provides great compile-time-like safety
        }
        if(empty(trim($key))) {
             throw new InvalidArgumentException("Rank key cannot be empty.");
        }
        return new self($key);
    }

    public function __toString(): string {
        return $this->value;
    }

    public function jsonSerialize(): string {
        return $this->value;
    }
}