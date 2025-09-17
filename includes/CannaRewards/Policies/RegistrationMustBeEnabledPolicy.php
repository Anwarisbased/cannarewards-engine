<?php
namespace CannaRewards\Policies;

use CannaRewards\Services\ConfigService;
use Exception;

class RegistrationMustBeEnabledPolicy implements ValidationPolicyInterface {
    public function __construct(private ConfigService $config) {}
    
    /**
     * This policy doesn't depend on a value, so the parameter is ignored.
     * It checks a global system state.
     */
    public function check($value): void {
        if (!$this->config->isRegistrationEnabled()) {
            throw new Exception('User registration is currently disabled.', 403);
        }
    }
}