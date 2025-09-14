<?php
namespace CannaRewards\Policies;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Infrastructure\WordPressApiWrapper;
use Exception;

class UserAccountIsUniquePolicy implements PolicyInterface {
    private WordPressApiWrapper $wp;

    public function __construct(WordPressApiWrapper $wp) {
        $this->wp = $wp;
    }

    public function check($command): void {
        // This policy only applies to the CreateUserCommand.
        if (!$command instanceof CreateUserCommand) {
            return;
        }

        $email_string = (string) $command->email;

        if ($this->wp->emailExists($email_string)) {
            // 409 Conflict is the correct HTTP status for a duplicate resource.
            throw new Exception('An account with that email already exists.', 409);
        }
    }
}