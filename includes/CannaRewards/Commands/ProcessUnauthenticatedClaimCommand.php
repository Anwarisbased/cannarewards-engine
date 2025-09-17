<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\RewardCode;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for an unauthenticated user attempting to claim a code.
 */
final class ProcessUnauthenticatedClaimCommand {
    public RewardCode $code;

    public function __construct(RewardCode $code) {
        $this->code = $code;
    }
}