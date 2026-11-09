<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductStockBatch;

/**
 * Tạo lô tồn đầu kỳ cho sản phẩm có tồn kho nhập trực tiếp (không qua phiếu nhập).
 *
 * Bán hàng trừ kho theo lô (StockBatchAllocator), nên sản phẩm có `stock` > 0
 * mà không có lô nào sẽ bị báo "Không đủ tồn kho theo lô" khi thanh toán.
 */
class OpeningStockBatchService
{
    public function create(Product $product, int $quantity, float $costPrice = 0, float $sellPrice = 0): ?ProductStockBatch
    {
        if ($quantity <= 0) {
            return null;
        }

        return ProductStockBatch::query()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'initial_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'cost_price' => round($costPrice, 2),
            'sell_price' => round($sellPrice, 2),
            'imported_at' => now()->toDateString(),
            'is_opening_balance' => true,
        ]);
    }
}
