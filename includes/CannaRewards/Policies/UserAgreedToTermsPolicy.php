<?php
namespace CannaRewards\Policies;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Services\ConfigService;
use Exception;

final class UserAgreedToTermsPolicy implements PolicyInterface {
    private ConfigService $configService;
    
    public function __construct(ConfigService $configService) {
        $this->configService = $configService;
    }
    
    /**
     * @throws Exception When user has not agreed to terms
     */
    public function check($command): void {
        // Extract the agreed_to_terms value from the command
        $agreedToTerms = false;
        if ($command instanceof CreateUserCommand || $command instanceof RegisterWithTokenCommand) {
            $agreedToTerms = $command->agreed_to_terms ?? false;
        }
        
        if (!$agreedToTerms && $this->configService->areTermsAndConditionsEnabled()) {
            throw new Exception("You must agree to the terms and conditions to register.");
        }
    }
}