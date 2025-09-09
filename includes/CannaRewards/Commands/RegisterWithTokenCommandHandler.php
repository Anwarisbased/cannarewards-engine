<?php
namespace CannaRewards\Commands;

use CannaRewards\Services\EconomyService;
use CannaRewards\Services\UserService;
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
        // --- FIX: We now receive a trusted EmailAddress object from the command ---
        $create_user_command = new \CannaRewards\Commands\CreateUserCommand(
            $command->email, // This is now an EmailAddress object
            $command->password,
            $command->first_name,
            $command->last_name,
            $command->phone,
            $command->agreed_to_terms,
            $command->agreed_to_marketing,
            $command->referral_code
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
        
        // 4. Log the user in by calling the official JWT endpoint internally.
        $request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $request->set_body_params([
            'username' => (string) $command->email, // Cast to string for the JWT plugin
            'password' => $command->password
        ]);

        $response = rest_do_request($request);

        if ($response->is_error()) {
            throw new Exception('Could not generate authentication token after registration.');
        }

        return $response->get_data();
    }
}