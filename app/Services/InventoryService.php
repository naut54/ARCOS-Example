<?php

declare(strict_types=1);

namespace App\Services;

use Arcos\Services\BaseService;

class InventoryService extends BaseService
{
    private array $stock = [
        1 => 42,
        2 => 0,
        3 => 15,
    ];

    public function getStock(int $productId): array
    {
        if (!isset($this->stock[$productId])) {
            return $this->down("No stock record found for product [{$productId}].");
        }

        $quantity = $this->stock[$productId];

        if ($quantity === 0) {
            return $this->degraded(
                reason:       "Product [{$productId}] is out of stock.",
                dependencies: ['stock' => 'degraded'],
            );
        }

        return array_merge(
            $this->ok(['stock' => 'ok']),
            ['quantity' => $quantity],
        );
    }

    public function health(): array
    {
        return $this->ok();
    }
}