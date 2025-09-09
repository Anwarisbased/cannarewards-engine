<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Repositories\UserRepository; // <-- IMPORT THE CORRECT REPOSITORY
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\EventFactory;
use CannaRewards\Includes\Event;
use Exception;

final class ProcessProductScanCommandHandler {
    private RewardCodeRepository $reward_code_repository;
    private ProductRepository $product_repository;
    private EconomyService $economy_service;
    private ActionLogService $action_log_service;
    private ActionLogRepository $action_log_repository;
    private RedeemRewardCommandHandler $redeem_reward_handler;
    private EventFactory $event_factory;
    private UserRepository $userRepository; // <-- ADD THE PROPERTY

    public function __construct(
        RewardCodeRepository $reward_code_repository,
        ProductRepository $product_repository,
        EconomyService $economy_service,
        ActionLogService $action_log_service,
        ActionLogRepository $action_log_repository,
        RedeemRewardCommandHandler $redeem_reward_handler,
        EventFactory $event_factory,
        UserRepository $userRepository // <-- ADD THE DEPENDENCY
    ) {
        $this->reward_code_repository = $reward_code_repository;
        $this->product_repository = $product_repository;
        $this->economy_service = $economy_service;
        $this->action_log_service = $action_log_service;
        $this->action_log_repository = $action_log_repository;
        $this->redeem_reward_handler = $redeem_reward_handler;
        $this->event_factory = $event_factory;
        $this->userRepository = $userRepository; // <-- ASSIGN THE PROPERTY
    }

    public function handle(ProcessProductScanCommand $command): array {
        $code_data = $this->reward_code_repository->findValidCode($command->code);
        if (!$code_data) {
            throw new Exception('This code is invalid or has already been used.');
        }

        $product_id = $this->product_repository->findIdBySku($code_data->sku);
        if (!$product_id) {
            throw new Exception('The product associated with this code could not be found.');
        }
        
        $scan_count = $this->action_log_repository->countUserActions($command->user_id, 'scan');
        $is_first_scan = ($scan_count === 0);

        $this->action_log_service->record($command->user_id, 'scan', $product_id);
        
        $product_post = get_post($product_id);
        $product_name = $product_post->post_title;
        $points_result = [];
        $message = '';

        if ($is_first_scan) {
            $options = get_option('canna_rewards_options', []);
            $welcome_reward_id = !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
            
            if ($welcome_reward_id > 0) {
                $redeem_command = new RedeemRewardCommand($command->user_id, $welcome_reward_id, []);
                $this->redeem_reward_handler->handle($redeem_command);
            }

            // --- THIS IS THE FIX ---
            // The method is on UserRepository, which we now have.
            $points_result['points_earned'] = 0;
            $points_result['new_points_balance'] = $this->userRepository->getPointsBalance($command->user_id);
            $message = 'Welcome! You have unlocked a special reward for your first scan.';

        } else {
            $base_points = $this->product_repository->getPointsAward($product_id);
            $grant_command = new GrantPointsCommand(
                $command->user_id,
                $base_points,
                'Product Scan: ' . $product_name
            );
            $points_result = $this->economy_service->handle($grant_command);
            $message = sprintf('You earned %d points for scanning %s!', $points_result['points_earned'], $product_name);
        }

        $this->reward_code_repository->markCodeAsUsed($code_data->id, $command->user_id);

        try {
            $event_payload = $this->event_factory->createProductScannedEvent($command->user_id, $product_post, $is_first_scan);
            Event::broadcast('product_scanned', $event_payload);
        } catch (Exception $e) {
            error_log("FATAL: Could not generate valid 'product_scanned' event: " . $e->getMessage());
        }

        return [
            'success'            => true,
            'message'            => $message,
            'points_earned'      => $points_result['points_earned'],
            'new_points_balance' => $points_result['new_points_balance'],
            'triggered_events'   => [],
        ];
    }
}