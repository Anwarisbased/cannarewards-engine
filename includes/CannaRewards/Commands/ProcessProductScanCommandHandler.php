<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\ContextBuilderService;
use CannaRewards\Includes\EventBusInterface;
use Exception;

final class ProcessProductScanCommandHandler {
    private RewardCodeRepository $rewardCodeRepo;
    private ProductRepository $productRepo;
    private ActionLogRepository $logRepo;
    private ActionLogService $logService;
    private EventBusInterface $eventBus;
    private ContextBuilderService $contextBuilder;

    public function __construct(
        RewardCodeRepository $rewardCodeRepo,
        ProductRepository $productRepo,
        ActionLogRepository $logRepo,
        ActionLogService $logService,
        EventBusInterface $eventBus,
        ContextBuilderService $contextBuilder
    ) {
        $this->rewardCodeRepo = $rewardCodeRepo;
        $this->productRepo = $productRepo;
        $this->logRepo = $logRepo;
        $this->logService = $logService;
        $this->eventBus = $eventBus;
        $this->contextBuilder = $contextBuilder;
    }

    public function handle(ProcessProductScanCommand $command): array {
        $code_data = $this->rewardCodeRepo->findValidCode($command->code);
        if (!$code_data) { throw new Exception('This code is invalid or has already been used.'); }
        
        $product_id = $this->productRepo->findIdBySku(\CannaRewards\Domain\ValueObjects\Sku::fromString($code_data->sku));
        if (!$product_id) { throw new Exception('The product associated with this code could not be found.'); }
        
        // --- REFACTORED LOGIC ---
        // 1. Log the scan to establish its history and count.
        $this->logService->record($command->userId->toInt(), 'scan', $product_id->toInt());
        $scan_count = $this->logRepo->countUserActions($command->userId->toInt(), 'scan');
        $is_first_scan = ($scan_count === 1);

        // 2. Mark the code as used immediately.
        $this->rewardCodeRepo->markCodeAsUsed($code_data->id, $command->userId);
        
        // 3. Build the rich context for the event.
        $context = $this->contextBuilder->build_event_context($command->userId->toInt(), get_post($product_id->toInt()));
        $context['is_first_scan'] = $is_first_scan;

        // 4. BROADCAST the event. The handler's job is done.
        // It doesn't know or care about points or gifts. It just announces what happened.
        $this->eventBus->broadcast('product_scanned', $context);
        
        // 5. Return a generic, immediate success message. The UI can update points/gifts later via websockets or polling.
        return [
            'success' => true,
            'message' => get_the_title($product_id->toInt()) . ' scanned successfully!',
            // We no longer return points data because this handler doesn't calculate it anymore.
        ];
        // --- END REFACTORED LOGIC ---
    }
}