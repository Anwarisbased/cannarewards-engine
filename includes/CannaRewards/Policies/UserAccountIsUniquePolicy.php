<?php
namespace CannaRewards\Policies;

use CannaRewards\Commands\CreateUserCommand;
use Exception;

class UserAccountIsUniquePolicy implements PolicyInterface {
    public function check($command): void {
        // This policy only applies to the CreateUserCommand.
        if (!$command instanceof CreateUserCommand) {
            return;
        }

        $email_string = (string) $command->email;

        if (email_exists($email_string)) {
            // 409 Conflict is the correct HTTP status for a duplicate resource.
            throw new Exception('An account with that email already exists.', 409);
        }
    }
}