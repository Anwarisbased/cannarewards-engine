<?php
namespace CannaRewards\Policies;

use CannaRewards\Domain\ValueObjects\Sku;
use CannaRewards\Repositories\ProductRepository;
use Exception;

final class ProductMustExistForSkuPolicy implements ValidationPolicyInterface {
    public function __construct(private ProductRepository $productRepository) {}

    /**
     * @param Sku $value
     * @throws Exception When SKU does not correspond to an actual product
     */
    public function check($value): void {
        if (!$value instanceof Sku) {
            throw new \InvalidArgumentException('This policy requires a Sku object.');
        }

        $productId = $this->productRepository->findIdBySku($value);
        if ($productId === null) {
            throw new Exception("The SKU {$value} does not correspond to an actual product.", 404); // 404 Not Found
        }
    }
}