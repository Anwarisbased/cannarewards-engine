<?php
namespace CannaRewards\Policies;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Domain\ValueObjects\RewardCode;
use CannaRewards\Commands\ProcessUnauthenticatedClaimCommand;
use Exception;

final class UnauthenticatedCodeIsValidPolicy implements PolicyInterface {
    private RewardCodeRepository $rewardCodeRepository;
    
    public function __construct(RewardCodeRepository $rewardCodeRepository) {
        $this->rewardCodeRepository = $rewardCodeRepository;
    }
    
    /**
     * @throws Exception When reward code is invalid or already used
     */
    public function check($command): void {
        // Extract the reward code from the command
        $rewardCode = null;
        if ($command instanceof ProcessUnauthenticatedClaimCommand) {
            $rewardCode = $command->code;
        }
        
        if ($rewardCode === null) {
            throw new Exception("Invalid command or reward code not found.");
        }
        
        $validCode = $this->rewardCodeRepository->findValidCode($rewardCode->toString());
        if ($validCode === null) {
            throw new Exception("The reward code {$rewardCode} is invalid or has already been used.");
        }
    }
}