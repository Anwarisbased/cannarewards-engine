<?php
namespace CannaRewards\Policies;

use CannaRewards\Commands\RedeemRewardCommand;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\UserRepository;
use Exception;

class UserCanAffordRedemptionPolicy implements PolicyInterface {
    private $product_repository;
    private $user_repository;

    public function __construct(ProductRepository $product_repo, UserRepository $user_repo) {
        $this->product_repository = $product_repo;
        $this->user_repository = $user_repo;
    }

    public function check($command): void {
        // This policy only cares about the RedeemRewardCommand.
        if (!$command instanceof RedeemRewardCommand) {
            return;
        }
        
        // Ignore free claims for the purpose of this policy.
        $options = get_option('canna_rewards_options', []);
        $welcome_reward_id = !empty($options['welcome_reward_product']) ? (int) $options['welcome_reward_product'] : 0;
        if ($command->product_id === $welcome_reward_id) {
            // A more robust check for first-scan might be needed, but for now this is fine.
            return;
        }

        $points_cost = $this->product_repository->getPointsCost($command->product_id);
        $current_balance = $this->user_repository->getPointsBalance($command->user_id);

        if ($current_balance < $points_cost) {
            // 402 Payment Required is the semantically correct HTTP code for insufficient funds/points.
            throw new Exception('Insufficient points.', 402);
        }
    }
}