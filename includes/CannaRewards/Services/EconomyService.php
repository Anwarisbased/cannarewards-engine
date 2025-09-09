<?php
namespace CannaRewards\Services;

use CannaRewards\Includes\Event;
use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use Exception;

// Exit if accessed directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Economy Service (Command Bus)
 *
 * The central "bank" for the platform. This service acts as a dispatcher,
 * delegating actions to dedicated handlers. It is the ONLY class allowed
 * to modify a user's points balance.
 */
class EconomyService {
    private $action_log_service;
    private $context_builder;
    private $user_service;
    private $command_map = []; // The map of Command => Handler
    private $reward_code_repository;
    private $product_repository;
    private $policy_map = []; // The map of Command => [Policies]
    private $container;     // The DI container to build policies

    public function __construct(
        ActionLogService $action_log_service,
        ContextBuilderService $context_builder,
        RewardCodeRepository $reward_code_repository,
        ProductRepository $product_repository,
        \CannaRewards\Container\DIContainer $container, // Inject the container
        array $policy_map = []                         // Inject the policy map
    ) {
        $this->action_log_service = $action_log_service;
        $this->context_builder    = $context_builder;
        $this->reward_code_repository = $reward_code_repository;
        $this->product_repository = $product_repository;
        $this->container = $container;
        $this->policy_map = $policy_map;
    }

    /**
     * Setter method to resolve circular dependency with UserService.
     */
    public function set_user_service(UserService $user_service) {
        $this->user_service = $user_service;
    }

    /**
     * Registers a handler for a specific command class.
     */
    public function registerCommandHandler(string $command_class, object $handler_instance): void {
        $this->command_map[$command_class] = $handler_instance;
    }

    /**
     * The main entry point to handle economy-related commands.
     */
    public function handle($command) {
        $command_class = get_class($command);

        // --- THE GAUNTLET ---
        // Before handling, run all registered policies for this command.
        $policies_for_command = $this->policy_map[$command_class] ?? [];
        foreach ($policies_for_command as $policy_class) {
            $policy = $this->container->get($policy_class);
            $policy->check($command); // This will throw an exception if a rule fails.
        }

        // If we get here, all policies passed. It's safe to proceed.
        if (!isset($this->command_map[$command_class])) {
            throw new Exception("No handler registered for command: {$command_class}");
        }
        $handler = $this->command_map[$command_class];
        return $handler->handle($command);
    }
    
    /**
     * Grants points to a user. This remains a core utility method.
     */
    public function grant_points( int $user_id, int $base_points, string $description, float $temp_multiplier = 1.0 ): array {
        $user_rank        = get_user_current_rank( $user_id );
        $rank_multiplier  = (float) ( $user_rank['multiplier'] ?? 1.0 );
        $final_multiplier = max( $rank_multiplier, $temp_multiplier );
        $points_to_grant  = floor( $base_points * $final_multiplier );

        $current_balance     = get_user_points_balance( $user_id );
        $new_balance         = $current_balance + $points_to_grant;
        update_user_meta( $user_id, '_canna_points_balance', $new_balance );

        $lifetime_points     = get_user_lifetime_points( $user_id );
        $new_lifetime_points = $lifetime_points + $points_to_grant;
        update_user_meta( $user_id, '_canna_lifetime_points', $new_lifetime_points );

        $log_meta_data = [
            'description'        => $description,
            'points_change'      => $points_to_grant,
            'new_balance'        => $new_balance,
            'base_points'        => $base_points,
            'multiplier_applied' => $final_multiplier > 1.0 ? $final_multiplier : null,
        ];
        $this->action_log_service->record( $user_id, 'points_granted', 0, $log_meta_data );

        $this->check_and_apply_rank_transition( $user_id );

        return [
            'points_earned'      => $points_to_grant,
            'new_points_balance' => $new_balance,
        ];
    }

    /**
     * Checks for rank transitions.
     */
    private function check_and_apply_rank_transition( int $user_id ) {
        $current_rank_key = get_user_meta( $user_id, '_canna_current_rank_key', true ) ?: 'member';
        
        $new_rank_data = get_user_current_rank( $user_id );
        $new_rank_key = $new_rank_data['key'];

        if ( $new_rank_key !== $current_rank_key ) {
            update_user_meta( $user_id, '_canna_current_rank_key', $new_rank_key );
            
            $all_ranks = canna_get_rank_structure();
            $old_rank_object = $all_ranks[ $current_rank_key ] ?? null;
            $new_rank_object = $all_ranks[ $new_rank_key ] ?? null;
            
            $context = $this->context_builder->build_event_context($user_id);

            Event::broadcast('user_rank_changed', array_merge($context, [
                'old_rank' => $old_rank_object,
                'new_rank' => $new_rank_object,
            ]));
        }
    }

    /**
     * The single, authoritative method for processing a QR code claim.
     */
    public function claimCode(int $user_id, string $code_to_claim): array {
        $code_data = $this->reward_code_repository->findValidCode($code_to_claim);
        if (!$code_data) {
            throw new Exception('This code is invalid or has already been used.');
        }

        $product_id = $this->product_repository->findIdBySku($code_data->sku);
        if (!$product_id) {
            throw new Exception('The product associated with this code could not be found.');
        }

        $this->reward_code_repository->markCodeAsUsed($code_data->id, $user_id);
        $this->action_log_service->record($user_id, 'scan', $product_id);

        $product_post = get_post($product_id);
        
        return [
            'product_id' => $product_id,
            'product_post' => $product_post,
            'base_points' => $this->product_repository->getPointsAward($product_id)
        ];
    }
}