<?php
namespace CannaRewards\Policies;

/**
 * Defines a contract for a policy that validates a specific piece of data (usually a Value Object).
 * It should throw a domain-specific exception on failure.
 */
interface ValidationPolicyInterface {
    public function check($value): void;
}