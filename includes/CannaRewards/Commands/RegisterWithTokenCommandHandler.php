<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\UserService;
use CannaRewards\Commands\ProcessProductScanCommand;
use Exception;

final class RegisterWithTokenCommandHandler {
    private $user_service;
    private $economy_service;

    public function __construct(UserService $user_service, EconomyService $economy_service) {
        $this->user_service = $user_service;
        $this->economy_service = $economy_service;
    }

    /**
     * @throws Exception on failure
     */
    public function handle(RegisterWithTokenCommand $command): array {
        $claim_code = get_transient('reg_token_' . $command->registration_token);
        if (false === $claim_code) {
            throw new Exception('Invalid or expired registration token.', 403);
        }

        // 1. Create the user first.
        $create_user_command = new \CannaRewards\Commands\CreateUserCommand(
            $command->email, $command->password, $command->first_name, $command->last_name,
            $command->phone, $command->agreed_to_terms, $command->agreed_to_marketing, $command->referral_code
        );

        $create_user_result = $this->user_service->handle($create_user_command);
        $new_user_id = $create_user_result['userId'];

        if (!$new_user_id) {
            throw new Exception('Failed to create user during token registration.');
        }

        // 2. Now that the user exists, dispatch the standard ProcessProductScanCommand.
        $process_scan_command = new ProcessProductScanCommand($new_user_id, $claim_code);
        $this->economy_service->handle($process_scan_command);

        // 3. All successful, delete the token so it can't be reused.
        delete_transient('reg_token_' . $command->registration_token);
        
        // --- THE ROBUST FIX ---
        // 4. Instead of manually creating a token, we make an internal REST request
        // to the official JWT login endpoint. This is guaranteed to work if the plugin is active.
        $request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $request->set_body_params([
            'username' => $command->email,
            'password' => $command->password
        ]);

        $response = rest_do_request($request);

        if ($response->is_error()) {
            // If the internal login fails for any reason, we throw.
            throw new Exception('Could not generate authentication token after registration.');
        }

        // The response from rest_do_request is exactly what we need to return.
        return $response->get_data();
    }
}