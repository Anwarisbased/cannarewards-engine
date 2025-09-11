<?php
namespace CannaRewards\Commands;

use CannaRewards\Repositories\RewardCodeRepository;
use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Services\ConfigService; // <<<--- IMPORT THE SERVICE
use CannaRewards\Infrastructure\WordPressApiWrapper; // <<<--- IMPORT THE WRAPPER
use Exception;

final class ProcessUnauthenticatedClaimCommandHandler {
    private $reward_code_repository;
    private $product_repository;
    private ConfigService $configService; // <<<--- ADD PROPERTY
    private WordPressApiWrapper $wp; // <<<--- ADD PROPERTY

    public function __construct(
        RewardCodeRepository $reward_code_repository,
        ProductRepository $product_repository,
        ConfigService $configService, // <<<--- INJECT DEPENDENCY
        WordPressApiWrapper $wp // <<<--- INJECT DEPENDENCY
    ) {
        $this->reward_code_repository = $reward_code_repository;
        $this->product_repository = $product_repository;
        $this->configService = $configService;
        $this->wp = $wp;
    }

    public function handle(ProcessUnauthenticatedClaimCommand $command): array {
        $code_data = $this->reward_code_repository->findValidCode($command->code);
        if (!$code_data) {
            throw new Exception('This code is invalid or has already been used.');
        }

        $product_id = $this->product_repository->findIdBySku($code_data->sku);
        if (!$product_id) {
            throw new Exception('The product associated with this code could not be found.');
        }

        $registration_token = bin2hex(random_bytes(32));
        // REFACTOR: Use the wrapper to set the transient
        $this->wp->setTransient('reg_token_' . $registration_token, $command->code, 15 * MINUTE_IN_SECONDS);
        
        // REFACTOR: Use the injected ConfigService
        $welcome_reward_id = $this->configService->getWelcomeRewardProductId();
        $product = $welcome_reward_id ? $this->wp->getProduct($welcome_reward_id) : null;

        return [
            'status'             => 'registration_required',
            'registration_token' => $registration_token,
            'reward_preview'     => [
                'id' => $product ? $product->get_id() : 0,
                'name' => $product ? $product->get_name() : 'Welcome Gift',
                'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : wc_placeholder_img_src(),
            ]
        ];
    }
}