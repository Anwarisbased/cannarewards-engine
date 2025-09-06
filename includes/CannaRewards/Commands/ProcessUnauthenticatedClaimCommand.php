<?php
namespace CannaRewards\Commands;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for an unauthenticated user attempting to claim a code.
 */
final class ProcessUnauthenticatedClaimCommand {
    public $code;

    public function __construct(string $code) {
        $this->code = $code;
    }
}