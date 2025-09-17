<?php
namespace CannaRewards\Commands;

use CannaRewards\Domain\ValueObjects\OrderId;
use CannaRewards\Domain\ValueObjects\Points;
use CannaRewards\DTO\RedeemRewardResultDTO;
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

    public function handle(RedeemRewardCommand $command): RedeemRewardResultDTO {
        $user_id = $command->userId->toInt();
        $product_id = $command->productId->toInt();
        
        $points_cost = $this->productRepo->getPointsCost($command->productId);
        $current_balance = $this->userRepo->getPointsBalance($command->userId);
        $new_balance = $current_balance - $points_cost;

        $order_id = $this->orderRepo->createFromRedemption($user_id, $product_id, $command->shippingDetails);
        if (!$order_id) { throw new Exception('Failed to create order for redemption.'); }

        $this->userRepo->saveShippingAddress($command->userId, $command->shippingDetails);
        $this->userRepo->savePointsAndRank($command->userId, $new_balance, $this->userRepo->getLifetimePoints($command->userId), $this->userRepo->getCurrentRankKey($command->userId));

        $product_name = $this->wp->getTheTitle($product_id);
        $log_meta_data = ['description' => 'Redeemed: ' . $product_name, 'points_change' => -$points_cost, 'new_balance' => $new_balance, 'order_id' => $order_id];
        $this->logService->record($user_id, 'redeem', $product_id, $log_meta_data);
        
        $full_context = $this->contextBuilder->build_event_context($user_id, $this->wp->getPost($product_id));
        
        // REFACTOR: Use the injected event bus
        $this->eventBus->broadcast('reward_redeemed', $full_context);
        
        return new RedeemRewardResultDTO(
            OrderId::fromInt($order_id),
            Points::fromInt($new_balance)
        );
    }
}