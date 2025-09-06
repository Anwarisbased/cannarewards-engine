<?php
namespace CannaRewards\Commands;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for processing a product scan.
 */
final class ProcessProductScanCommand {
    public $user_id;
    public $code;

    public function __construct(int $user_id, string $code) {
        $this->user_id = $user_id;
        $this->code = $code;
    }
}