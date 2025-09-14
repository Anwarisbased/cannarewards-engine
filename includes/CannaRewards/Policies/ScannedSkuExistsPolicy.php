<?php
namespace CannaRewards\Policies;

use CannaRewards\Repositories\ProductRepository;
use CannaRewards\Domain\ValueObjects\SKU;
use CannaRewards\Commands\ProcessProductScanCommand;
use CannaRewards\Repositories\RewardCodeRepository;
use Exception;

final class ScannedSkuExistsPolicy implements PolicyInterface {
    private ProductRepository $productRepository;
    private RewardCodeRepository $rewardCodeRepository;
    
    public function __construct(ProductRepository $productRepository, RewardCodeRepository $rewardCodeRepository) {
        $this->productRepository = $productRepository;
        $this->rewardCodeRepository = $rewardCodeRepository;
    }
    
    /**
     * @throws Exception When SKU does not correspond to an actual product
     */
    public function check($command): void {
        // This policy only cares about the ProcessProductScanCommand.
        if (!$command instanceof ProcessProductScanCommand) {
            return;
        }
        
        // First, we need to validate the reward code to get the SKU
        $code_data = $this->rewardCodeRepository->findValidCode($command->code->toString());
        if (!$code_data) {
            throw new Exception('This code is invalid or has already been used.');
        }
        
        // Now validate that the SKU corresponds to an actual product
        $sku_vo = SKU::fromString($code_data->sku);
        $productId = $this->productRepository->findIdBySku($sku_vo->toString());
        if ($productId === null) {
            throw new Exception("The SKU {$sku_vo} does not correspond to an actual product.");
        }
    }
}