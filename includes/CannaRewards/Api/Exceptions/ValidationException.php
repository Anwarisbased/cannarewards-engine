
<?php
namespace CannaRewards\Api\Exceptions;

class ValidationException extends \Exception {
    private array $errors;

    public function __construct(array $errors, string $message = "The given data was invalid.", int $code = 422) {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}