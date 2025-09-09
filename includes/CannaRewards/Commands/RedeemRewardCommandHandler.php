<?php
namespace CannaRewards\Commands;

use CannaRewards\Commands\RedeemRewardCommand;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Repositories\OrderRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\ContextBuilderService;
use CannaRewards\Includes\Event;
use Exception;

final class RedeemRewardCommandHandler {
    private $product_repository;
    private $user_repository;
    private $order_repository;
    private $action_log_repository;
    private $action_log_service;
    private $context_builder;

    public function __construct(
        ProductRepository $product_repository,
        UserRepository $user_repository,
        OrderRepository $order_repository,
        ActionLogService $action_log_service,
        ContextBuilderService $context_builder,
        ActionLogRepository $action_log_repository
    ) {
        $this->product_repository = $product_repository;
        $this->user_repository = $user_repository;
        $this->order_repository = $order_repository;
        $this->action_log_repository = $action_log_repository;
        $this->action_log_service = $action_log_service;
        $this->context_builder = $context_builder;
    }

    public function handle(RedeemRewardCommand $command): array {
        $user_id = $command->user_id;
        $product_id = $command->product_id;
        $is_free_claim = false;

        $options = get_option('canna_rewards_options', []);
        $welcome_reward_id = !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
        $referral_gift_id = !empty($options['referral_signup_gift']) ? (int) $options['referral_signup_gift'] : 0;

        if ($product_id === $welcome_reward_id || $product_id === $referral_gift_id) {
            $scan_count = $this->action_log_repository->countUserActions($user_id, 'scan');
            if ($scan_count <= 1) {
                $is_free_claim = true;
            }
        }
        
        $points_cost = $this->product_repository->getPointsCost($product_id);
        $current_balance = $this->user_repository->getPointsBalance($user_id);
        $new_balance = $current_balance;

        if (!$is_free_claim) {
            if ($current_balance < $points_cost) throw new Exception('Insufficient points.', 1);
            $new_balance = $current_balance - $points_cost;
        }

        $required_rank = $this->product_repository->getRequiredRank($product_id);
        if ($required_rank) { /* Future rank check logic */ }

        // --- THIS IS THE FIX ---
        // Map the incoming camelCase JSON keys to the snake_case keys WooCommerce expects.
        // Also, add default values for fields WooCommerce requires, like country.
        $user_data = get_userdata($user_id);
        $address_for_order = [];
        if (!empty($command->shipping_details)) {
            $address_for_order = [
                'first_name' => $command->shipping_details['firstName'] ?? '',
                'last_name'  => $command->shipping_details['lastName'] ?? '',
                'address_1'  => $command->shipping_details['address1'] ?? '',
                'city'       => $command->shipping_details['city'] ?? '',
                'state'      => $command->shipping_details['state'] ?? '',
                'postcode'   => $command->shipping_details['zip'] ?? '',
                'country'    => 'US', // Default country, as it's often required.
                'email'      => $user_data->user_email,
            ];
        }

        $order_id = $this->order_repository->createFromRedemption($user_id, $product_id, $address_for_order);
        if (!$order_id) throw new Exception('Failed to create order for redemption.');

        if (!empty($command->shipping_details)) {
            $this->user_repository->saveShippingAddress($user_id, $command->shipping_details);
        }

        $points_to_deduct = $is_free_claim ? 0 : $points_cost;
        $this->user_repository->savePointsAndRank($user_id, $new_balance, $this->user_repository->getLifetimePoints($user_id), $this->user_repository->getCurrentRankKey($user_id));

        $product = get_post($product_id);
        $product_name = $product ? $product->post_title : 'reward';
        $log_meta_data = ['description' => 'Redeemed: ' . $product_name, 'points_change' => -$points_to_deduct, 'new_balance' => $new_balance, 'order_id' => $order_id];
        $this->action_log_service->record($user_id, 'redeem', $product_id, $log_meta_data);
        
        $full_context = $this->context_builder->build_event_context($user_id, $product);
        Event::broadcast('reward_redeemed', $full_context);
        
        return ['success' => true, 'order_id' => $order_id, 'new_points_balance' => $new_balance];
    }
}