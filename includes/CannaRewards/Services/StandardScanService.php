<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\GrantPointsCommand;
use CannaRewards\Commands\GrantPointsCommandHandler;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Repositories\ProductRepository;

final class StandardScanService {
    private ProductRepository $productRepo;
    private GrantPointsCommandHandler $grantPointsHandler;
    private EventBusInterface $eventBus;

    public function __construct(
        ProductRepository $productRepo,
        GrantPointsCommandHandler $grantPointsHandler,
        EventBusInterface $eventBus
    ) {
        $this->productRepo = $productRepo;
        $this->grantPointsHandler = $grantPointsHandler;
        $this->eventBus = $eventBus;

        $this->eventBus->listen('product_scanned', [$this, 'grantPointsOnScan']);
    }

    public function grantPointsOnScan(array $payload): void {
        $user_id = $payload['user_snapshot']['identity']['user_id'] ?? 0;
        $is_first_scan = $payload['is_first_scan'] ?? false;
        $product_id = $payload['product_snapshot']['identity']['product_id'] ?? 0;
        $product_name = $payload['product_snapshot']['identity']['product_name'] ?? 'product';

        // Only grant points if it's NOT the first scan and we have a valid user/product
        if ($user_id > 0 && $product_id > 0 && !$is_first_scan) {
            $base_points = $this->productRepo->getPointsAward(\CannaRewards\Domain\ValueObjects\ProductId::fromInt($product_id));
            if ($base_points > 0) {
                $command = new GrantPointsCommand(
                    \CannaRewards\Domain\ValueObjects\UserId::fromInt($user_id),
                    \CannaRewards\Domain\ValueObjects\Points::fromInt($base_points),
                    'Product Scan: ' . $product_name
                );
                $this->grantPointsHandler->handle($command);
            }
        }
    }
}