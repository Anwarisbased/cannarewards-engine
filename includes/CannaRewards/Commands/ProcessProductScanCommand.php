<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Domain\ValueObjects\RewardCode;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Command DTO for processing a product scan.
 */
final class ProcessProductScanCommand {
    public UserId $userId;
    public RewardCode $code;

    public function __construct(UserId $userId, RewardCode $code) {
        $this->userId = $userId;
        $this->code = $code;
    }
}