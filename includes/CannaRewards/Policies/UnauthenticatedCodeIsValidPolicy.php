<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\RewardCode;
use CannaRewards\Repositories\RewardCodeRepository;
use Exception;

final class UnauthenticatedCodeIsValidPolicy implements ValidationPolicyInterface {
    private RewardCodeRepository $rewardCodeRepository;
    
    public function __construct(RewardCodeRepository $rewardCodeRepository) {
        $this->rewardCodeRepository = $rewardCodeRepository;
    }
    
    public function check($value): void {
        if (!$value instanceof RewardCode) {
            throw new \InvalidArgumentException('This policy requires a RewardCode object.');
        }
        
        $validCode = $this->rewardCodeRepository->findValidCode($value);
        if ($validCode === null) {
            // Add the 409 status code to the exception
            error_log("Throwing exception with code 409 for invalid code: " . $value);
            throw new Exception("The reward code {$value} is invalid or has already been used.", 409);
        }
    }
}