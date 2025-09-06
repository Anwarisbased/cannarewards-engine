<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\CreateUserCommand;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\ReferralService;
use CannaRewards\Services\CDPService;
use CannaRewards\Includes\Event;
use Exception;

final class CreateUserCommandHandler {
    private $user_repository;
    private $cdp_service;
    private $referral_service;

    public function __construct(UserRepository $user_repository, CDPService $cdp_service) {
        $this->user_repository = $user_repository;
        $this->cdp_service = $cdp_service;
    }

    public function setReferralService(ReferralService $referral_service): void {
        $this->referral_service = $referral_service;
    }

    public function handle(CreateUserCommand $command): array {
        if (!get_option('users_can_register')) throw new Exception('User registration is currently disabled.', 503);
        if (empty($command->email) || empty($command->password) || !is_email($command->email)) throw new Exception('A valid email and password are required.', 400);
        if (email_exists($command->email)) throw new Exception('An account with that email already exists.', 409);

        $user_id = wp_insert_user([
            'user_login' => $command->email, 'user_email' => $command->email, 'user_pass'  => $command->password,
            'first_name' => $command->first_name, 'last_name'  => $command->last_name, 'role' => 'subscriber'
        ]);

        if (is_wp_error($user_id)) throw new Exception($user_id->get_error_message(), 500);

        update_user_meta($user_id, 'phone_number', $command->phone);
        update_user_meta($user_id, 'marketing_consent', $command->agreed_to_marketing);
        update_user_meta($user_id, '_age_gate_confirmed_at', current_time('mysql', 1));
        $this->user_repository->savePointsAndRank($user_id, 0, 0, 'member');
        
        $this->referral_service->generate_code_for_new_user($user_id, $command->first_name);

        if ($command->referral_code) {
            $this->referral_service->process_new_user_referral($user_id, $command->referral_code);
        }
        
        Event::broadcast('user_created', ['user_id' => $user_id, 'referral_code' => $command->referral_code]);
        $this->cdp_service->track($user_id, 'user_created', ['signup_method' => 'password', 'referral_code_used' => $command->referral_code]);

        return ['success' => true, 'message' => 'Registration successful.', 'userId' => $user_id];
    }
}