<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Repositories\OrderRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\ContextBuilderService;
use CannaRewards\Includes\EventBusInterface; // <<<--- IMPORT INTERFACE
use CannaRewards\Infrastructure\WordPressApiWrapper; // <<<--- IMPORT WRAPPER
use Exception;

final class RedeemRewardCommandHandler {
    private ProductRepository $productRepo;
    private UserRepository $userRepo;
    private OrderRepository $orderRepo;
    private ActionLogRepository $logRepo;
    private ActionLogService $logService;
    private ContextBuilderService $contextBuilder;
    private EventBusInterface $eventBus; // <<<--- ADD PROPERTY
    private WordPressApiWrapper $wp; // <<<--- ADD WRAPPER PROPERTY

    public function __construct(
        ProductRepository $productRepo,
        UserRepository $userRepo,
        OrderRepository $orderRepo,
        ActionLogService $logService,
        ContextBuilderService $contextBuilder,
        ActionLogRepository $logRepo,
        EventBusInterface $eventBus, // <<<--- ADD DEPENDENCY
        WordPressApiWrapper $wp // <<<--- ADD WRAPPER DEPENDENCY
    ) {
        $this->productRepo = $productRepo;
        $this->userRepo = $userRepo;
        $this->orderRepo = $orderRepo;
        $this->logService = $logService;
        $this->contextBuilder = $contextBuilder;
        $this->logRepo = $logRepo;
        $this->eventBus = $eventBus; // <<<--- ASSIGN DEPENDENCY
        $this->wp = $wp; // <<<--- ASSIGN WRAPPER
    }

    public function handle(RedeemRewardCommand $command): array {
        $user_id = $command->user_id;
        $product_id = $command->product_id;
        
        $points_cost = $this->productRepo->getPointsCost($product_id);
        $current_balance = $this->userRepo->getPointsBalance($user_id);
        $new_balance = $current_balance - $points_cost;

        $order_id = $this->orderRepo->createFromRedemption($user_id, $product_id, $command->shipping_details);
        if (!$order_id) { throw new Exception('Failed to create order for redemption.'); }

        $this->userRepo->saveShippingAddress($user_id, $command->shipping_details);
        $this->userRepo->savePointsAndRank($user_id, $new_balance, $this->userRepo->getLifetimePoints($user_id), $this->userRepo->getCurrentRankKey($user_id));

        $product_name = $this->wp->getTheTitle($product_id);
        $log_meta_data = ['description' => 'Redeemed: ' . $product_name, 'points_change' => -$points_cost, 'new_balance' => $new_balance, 'order_id' => $order_id];
        $this->logService->record($user_id, 'redeem', $product_id, $log_meta_data);
        
        $full_context = $this->contextBuilder->build_event_context($user_id, $this->wp->getPost($product_id));
        
        // REFACTOR: Use the injected event bus
        $this->eventBus->broadcast('reward_redeemed', $full_context);
        
        return ['success' => true, 'order_id' => $order_id, 'new_points_balance' => $new_balance];
    }
}