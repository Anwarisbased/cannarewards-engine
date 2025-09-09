<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\ContextBuilderService;
use CannaRewards\Includes\Event;
use Exception;

final class ProcessProductScanCommandHandler {
    private $reward_code_repository;
    private $product_repository;
    private $economy_service;
    private $action_log_service;
    private $context_builder;
    private $action_log_repository;
    private $redeem_reward_handler; // <-- ADD PROPERTY

    public function __construct(
        RewardCodeRepository $reward_code_repository,
        ProductRepository $product_repository,
        EconomyService $economy_service,
        ContextBuilderService $context_builder,
        ActionLogService $action_log_service,
        ActionLogRepository $action_log_repository,
        RedeemRewardCommandHandler $redeem_reward_handler // <-- ADD TO CONSTRUCTOR
    ) {
        $this->reward_code_repository = $reward_code_repository;
        $this->product_repository = $product_repository;
        $this->economy_service = $economy_service;
        $this->context_builder = $context_builder;
        $this->action_log_service = $action_log_service;
        $this->action_log_repository = $action_log_repository;
        $this->redeem_reward_handler = $redeem_reward_handler; // <-- ASSIGN PROPERTY
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

        if ($is_first_scan) {
            // --- THIS IS THE FIX ---
            // On first scan, automatically trigger a redemption for the welcome gift.
            $options = get_option('canna_rewards_options', []);
            $welcome_reward_id = !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
            
            if ($welcome_reward_id > 0) {
                // To create the order, we dispatch a RedeemRewardCommand internally.
                $redeem_command = new RedeemRewardCommand($command->user_id, $welcome_reward_id, []);
                $this->redeem_reward_handler->handle($redeem_command);
            }

            $user_repo = new UserRepository();
            $points_result['points_earned'] = 0;
            $points_result['new_points_balance'] = $user_repo->getPointsBalance($command->user_id);
            $message = 'Welcome! You have unlocked a special reward for your first scan.';

        } else {
            // It's a subsequent scan, so grant points as normal.
            $base_points = $this->product_repository->getPointsAward($product_id);
            $description = 'Product Scan: ' . $product_name;
            $points_result = $this->economy_service->grant_points($command->user_id, $base_points, $description);
            $message = sprintf('You earned %d points for scanning %s!', $points_result['points_earned'], $product_name);
        }

        $this->reward_code_repository->markCodeAsUsed($code_data->id, $command->user_id);

        $full_context = $this->context_builder->build_event_context($command->user_id, $product_post);
        $full_context['is_first_scan'] = $is_first_scan; 
        
        Event::broadcast('product_scanned', $full_context);

        return [
            'success'            => true,
            'message'            => $message,
            'points_earned'      => $points_result['points_earned'],
            'new_points_balance' => $points_result['new_points_balance'],
            'triggered_events'   => [],
        ];
    }
}