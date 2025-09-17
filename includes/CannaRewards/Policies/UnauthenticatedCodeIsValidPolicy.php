<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\RewardCode;
use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Commands\ProcessUnauthenticatedClaimCommand;
use Exception;

final class UnauthenticatedCodeIsValidPolicy implements ValidationPolicyInterface {
    private RewardCodeRepository $rewardCodeRepository;
    
    public function __construct(RewardCodeRepository $rewardCodeRepository) {
        $this->rewardCodeRepository = $rewardCodeRepository;
    }
    
    /**
     * @throws Exception When reward code is invalid or already used
     */
    public function check($value): void {
        if (!$value instanceof RewardCode) {
            throw new \InvalidArgumentException('This policy requires a RewardCode object.');
        }
        
        $validCode = $this->rewardCodeRepository->findValidCode($value);
        if ($validCode === null) {
            throw new Exception("The reward code {$value} is invalid or has already been used.");
        }
    }
}