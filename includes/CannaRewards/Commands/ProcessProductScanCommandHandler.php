<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Repositories\ActionLogRepository;
use CannaRewards\Repositories\UserRepository;
use CannaRewards\Services\EconomyService;
use CannaRewards\Services\ActionLogService;
use CannaRewards\Services\ContextBuilderService; // <<<--- IMPORT SERVICE
use CannaRewards\Includes\EventBusInterface; // <<<--- IMPORT INTERFACE
use Exception;

final class ProcessProductScanCommandHandler {
    private RewardCodeRepository $rewardCodeRepo;
    private ProductRepository $productRepo;
    private ActionLogRepository $logRepo;
    private UserRepository $userRepo;
    private EconomyService $economyService;
    private ActionLogService $logService;
    private RedeemRewardCommandHandler $redeemHandler;
    private EventBusInterface $eventBus; // <<<--- ADD PROPERTY
    private ContextBuilderService $contextBuilder; // <<<--- ADD PROPERTY

    public function __construct(
        RewardCodeRepository $rewardCodeRepo,
        ProductRepository $productRepo,
        ActionLogRepository $logRepo,
        UserRepository $userRepo,
        EconomyService $economyService,
        ActionLogService $logService,
        RedeemRewardCommandHandler $redeemHandler,
        EventBusInterface $eventBus, // <<<--- ADD DEPENDENCY
        ContextBuilderService $contextBuilder // <<<--- ADD DEPENDENCY
    ) {
        $this->rewardCodeRepo = $rewardCodeRepo;
        $this->productRepo = $productRepo;
        $this->logRepo = $logRepo;
        $this->userRepo = $userRepo;
        $this->economyService = $economyService;
        $this->logService = $logService;
        $this->redeemHandler = $redeemHandler;
        $this->eventBus = $eventBus; // <<<--- ASSIGN DEPENDENCY
        $this->contextBuilder = $contextBuilder; // <<<--- ASSIGN DEPENDENCY
    }

    public function handle(ProcessProductScanCommand $command): array {
        $code_data = $this->rewardCodeRepo->findValidCode($command->code);
        if (!$code_data) { throw new Exception('This code is invalid or has already been used.'); }
        $product_id = $this->productRepo->findIdBySku($code_data->sku);
        if (!$product_id) { throw new Exception('The product associated with this code could not be found.'); }
        
        // We log the scan first to ensure the count is correct for the first-scan check
        $this->logService->record($command->user_id, 'scan', $product_id);
        $scan_count = $this->logRepo->countUserActions($command->user_id, 'scan');
        $is_first_scan = ($scan_count === 1);

        $product_name = get_the_title($product_id);
        $points_result = ['points_earned' => 0, 'new_points_balance' => $this->userRepo->getPointsBalance($command->user_id)];
        $message = '';
        if ($is_first_scan) {
            $welcome_reward_id = (int) get_option('canna_rewards_options')['welcome_reward_product'];
            if ($welcome_reward_id > 0) {
                $this->redeemHandler->handle(new RedeemRewardCommand($command->user_id, $welcome_reward_id, []));
            }
            $message = 'Welcome! You have unlocked a special reward for your first scan.';
        } else {
            $base_points = $this->productRepo->getPointsAward($product_id);
            if ($base_points > 0) {
                $grant_command = new GrantPointsCommand($command->user_id, $base_points, 'Product Scan: ' . $product_name);
                $points_result = $this->economyService->handle($grant_command);
                $message = sprintf('You earned %d points for scanning %s!', $points_result['points_earned'], $product_name);
            } else {
                 $message = sprintf('Scanned %s successfully!', $product_name);
            }
        }
        $this->rewardCodeRepo->markCodeAsUsed($code_data->id, $command->user_id);
        
        // REFACTOR: Build context and use the injected event bus
        $context = $this->contextBuilder->build_event_context($command->user_id, get_post($product_id));
        $context['is_first_scan'] = $is_first_scan;
        $this->eventBus->broadcast('product_scanned', $context);
        
        return ['success' => true, 'message' => $message] + $points_result;
    }
}