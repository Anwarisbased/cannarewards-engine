<?php
namespace CannaRewards\Services;

use CannaRewards\Commands\RedeemRewardCommand;
use CannaRewards\Commands\RedeemRewardCommandHandler;
use CannaRewards\Includes\EventBusInterface;
use CannaRewards\Domain\ValueObjects\UserId;
use CannaRewards\Domain\ValueObjects\ProductId;

final class FirstScanBonusService {
    private ConfigService $configService;
    private RedeemRewardCommandHandler $redeemHandler;
    private EventBusInterface $eventBus;

    public function __construct(
        ConfigService $configService,
        RedeemRewardCommandHandler $redeemHandler,
        EventBusInterface $eventBus
    ) {
        $this->configService = $configService;
        $this->redeemHandler = $redeemHandler;
        $this->eventBus = $eventBus;

        // This service listens for the product_scanned event.
        $this->eventBus->listen('product_scanned', [$this, 'awardBonusOnFirstScan']);
    }

    public function awardBonusOnFirstScan(array $payload): void {
        $user_id = $payload['user_snapshot']['identity']['user_id'] ?? 0;
        $is_first_scan = $payload['is_first_scan'] ?? false;

        if ($user_id > 0 && $is_first_scan) {
            $welcome_reward_id = $this->configService->getWelcomeRewardProductId();
            if ($welcome_reward_id > 0) {
                $this->redeemHandler->handle(new RedeemRewardCommand(UserId::fromInt($user_id), ProductId::fromInt($welcome_reward_id), []));
            }
        }
    }
}