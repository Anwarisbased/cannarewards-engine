<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\ProcessProductScanCommand;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class ClaimRequest extends FormRequest {

    protected function rules(): array {
        return [
            'code' => ['required'],
        ];
    }

    public function to_command(int $user_id): ProcessProductScanCommand {
        $validated = $this->validated();

        return new ProcessProductScanCommand(
            $user_id,
            $validated['code']
        );
    }
}