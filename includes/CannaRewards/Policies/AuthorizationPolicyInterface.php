<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\UserId;

/**
 * Defines a contract for a policy that checks if a user is authorized to perform an action.
 * It should throw a domain-specific exception on failure.
 */
interface AuthorizationPolicyInterface {
    public function check(UserId $userId, object $command): void;
}