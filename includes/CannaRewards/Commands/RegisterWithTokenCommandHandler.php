<?php
namespace CannaRewards\Commands;

use CannaRewards\Services\UserService;
use CannaRewards\Services\EconomyService;
use Exception;

final class RegisterWithTokenCommandHandler {
    private UserService $userService;
    private EconomyService $economyService; // We still need this to dispatch the command

    public function __construct(UserService $userService, EconomyService $economyService) {
        $this->userService = $userService;
        $this->economyService = $economyService;
    }

    /**
     * @throws Exception on failure
     */
    public function handle(RegisterWithTokenCommand $command): array {
        $claim_code = get_transient('reg_token_' . $command->registration_token);
        if (false === $claim_code) {
            throw new Exception('Invalid or expired registration token.', 403);
        }

        // 1. Create the user.
        $create_user_command = new \CannaRewards\Commands\CreateUserCommand(
            $command->email,
            $command->password,
            $command->first_name,
            $command->last_name,
            $command->phone,
            $command->agreed_to_terms,
            $command->agreed_to_marketing,
            $command->referral_code
        );
        $create_user_result = $this->userService->handle($create_user_command);
        $new_user_id = $create_user_result['userId'];

        if (!$new_user_id) {
            throw new Exception('Failed to create user during token registration.');
        }

        // 2. Now that the user exists, dispatch the standard ProcessProductScanCommand.
        // This command is now simple and just broadcasts an event. Our new services will listen and
        // correctly identify it as a first scan.
        $process_scan_command = new ProcessProductScanCommand($new_user_id, $claim_code);
        $this->economyService->handle($process_scan_command);

        // 3. All successful, delete the token.
        delete_transient('reg_token_' . $command->registration_token);
        
        // 4. Log the user in.
        $request = new \WP_REST_Request('POST', '/jwt-auth/v1/token');
        $request->set_body_params([
            'username' => (string) $command->email,
            'password' => $command->password
        ]);
        $response = rest_do_request($request);

        if ($response->is_error()) {
            throw new Exception('Could not generate authentication token after registration.');
        }

        return $response->get_data();
    }
}