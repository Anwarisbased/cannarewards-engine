<?php
namespace CannaRewards\Api\Requests;

use CannaRewards\Api\FormRequest;
use CannaRewards\Commands\ProcessUnauthenticatedClaimCommand;
use CannaRewards\Domain\ValueObjects\RewardCode;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}


class UnauthenticatedClaimRequest extends FormRequest {

    protected function rules(): array {
        return [
            'code' => ['required'],
        ];
    }

    public function to_command(): ProcessUnauthenticatedClaimCommand {
        $validated = $this->validated();

        return new ProcessUnauthenticatedClaimCommand(
            RewardCode::fromString($validated['code'])
        );
    }
}