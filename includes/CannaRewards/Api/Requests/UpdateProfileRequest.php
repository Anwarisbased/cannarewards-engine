<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\UpdateProfileCommand;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class UpdateProfileRequest extends FormRequest {

    protected function rules(): array {
        return [
            // We don't define strict rules here since profile updates can be partial
            // The validation will happen in the service layer based on what fields are provided
        ];
    }

    public function to_command(int $user_id): UpdateProfileCommand {
        $validated = $this->validated();

        return new UpdateProfileCommand(
            $user_id,
            $validated
        );
    }
}