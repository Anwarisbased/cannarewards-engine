<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\EmailAddress;
use CannaRewards\Infrastructure\WordPressApiWrapper;
use Exception;

class EmailAddressMustBeUniquePolicy implements ValidationPolicyInterface {
    public function __construct(private WordPressApiWrapper $wp) {}

    /**
     * @param EmailAddress $value
     */
    public function check($value): void {
        if (!$value instanceof EmailAddress) {
            throw new \InvalidArgumentException('This policy requires an EmailAddress object.');
        }

        if ($this->wp->emailExists((string) $value)) {
            throw new Exception('An account with that email already exists.', 409);
        }
    }
}