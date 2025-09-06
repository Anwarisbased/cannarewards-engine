<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\RegisterWithTokenCommand;
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\UserService;
// --- START: ADD THIS IMPORT ---
use CannaRewards\Commands\ProcessProductScanCommand;
// --- END: ADD THIS IMPORT ---
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

        // --- START: REFACTORED CLAIM LOGIC ---
        // 2. Now that the user exists, dispatch the standard ProcessProductScanCommand.
        // This ensures the scan goes through the SAME logic as an authenticated user's scan,
        // which includes our new "is this a first scan?" check.
        $process_scan_command = new ProcessProductScanCommand($new_user_id, $claim_code);
        $this->economy_service->handle($process_scan_command);
        // --- END: REFACTORED CLAIM LOGIC ---

        // 3. All successful, delete the token so it can't be reused.
        delete_transient('reg_token_' . $command->registration_token);
        
        // 4. Return JWT for auto-login.
        $token = '';
        // This is a temporary measure, ideally you'd get this from the login service
        if (function_exists('jwt_auth_create_token')) {
            $user = get_user_by('ID', $new_user_id);
            if ($user) {
                // Manually create a token since we are bypassing the normal login flow.
                // We need to simulate the response the JWT plugin would give.
                $payload = [
                    'token'             => \JWT_Auth\Token::create($user->ID),
                    'user_email'        => $user->user_email,
                    'user_nicename'     => $user->user_nicename,
                    'user_display_name' => $user->display_name,
                ];
                 return ['success' => true, 'token' => $payload['token']];
            }
        }

        return ['success' => true, 'token' => null];
    }
}