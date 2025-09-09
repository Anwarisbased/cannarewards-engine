<?php
namespace CannaRewards\Policies;

/**
 * Defines the contract for a business rule check that runs before a command is handled.
 * The check method should throw a domain-specific exception on failure.
 */
interface PolicyInterface {
    public function check($command): void;
}