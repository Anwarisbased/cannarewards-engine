<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\RewardCode;
use CannaRewards\Repositories\RewardCodeRepository;
use Exception;

final class RewardCodeMustBeValidPolicy implements ValidationPolicyInterface {
    public function __construct(private RewardCodeRepository $rewardCodeRepository) {}
    
    /**
     * @param RewardCode $value
     * @throws Exception When reward code is invalid or already used
     */
    public function check($value): void {
        if (!$value instanceof RewardCode) {
            throw new \InvalidArgumentException('This policy requires a RewardCode object.');
        }
        
        $codeData = $this->rewardCodeRepository->findValidCode($value);
        if ($codeData === null) {
            throw new Exception("The reward code {$value} is invalid or has already been used.", 409); // 409 Conflict
        }
    }
}