<?php

namespace App\Services\Stock;

use App\Models\ProductStockBatch;
use App\Models\SaleItem;
use App\Models\SaleItemGift;
use Illuminate\Validation\ValidationException;

class StockBatchAllocator
{
    /** @return array{total_cost: float, allocated_quantity: int} */
    public function allocate(SaleItem|SaleItemGift $line, int $productId, ?int $variantId, int $quantity): array
    {
        $remaining = max(0, $quantity);
        $totalCost = 0.0;
        $batches = ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->where('remaining_quantity', '>', 0)
            ->orderBy('imported_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $taken = min($remaining, (int) $batch->remaining_quantity);
            $batch->decrement('remaining_quantity', $taken);
            $line->stockBatchAllocations()->create([
                'product_stock_batch_id' => $batch->id,
                'quantity' => $taken,
                'unit_cost' => $batch->cost_price,
            ]);
            $totalCost += $taken * (float) $batch->cost_price;
            $remaining -= $taken;
        }

        // Hàng có tồn từ trước khi quản lý theo lô (tạo sản phẩm, import Excel...) chưa có lô nào.
        // Không phân bổ được thì để luồng gọi tự kiểm tra và trừ theo cột tồn kho như trước,
        // thay vì báo thiếu tồn theo lô khiến hàng đang có tồn không bán được.
        if ($remaining > 0 && $batches->isEmpty() && ! $this->hasAnyBatch($productId, $variantId)) {
            return ['total_cost' => 0.0, 'allocated_quantity' => 0];
        }

        if ($remaining > 0 && ! filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['items' => 'Không đủ tồn kho theo lô. Vui lòng tải lại trang và thử lại.']);
        }

        return ['total_cost' => $totalCost, 'allocated_quantity' => $quantity - $remaining];
    }

    private function hasAnyBatch(int $productId, ?int $variantId): bool
    {
        return ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->exists();
    }

    public function restore(SaleItem|SaleItemGift $line): void
    {
        $line->loadMissing('stockBatchAllocations');
        if ($line->stockBatchAllocations->isEmpty()) {
            // Hóa đơn tạo trước khi bật quản lý theo lô không có lịch sử phân bổ.
            // Khi hủy/sửa, tạo lô phục hồi để số tồn tổng và tồn theo lô không lệch.
            $baseQuantity = max(1, (int) ($line->base_quantity ?: $line->quantity));
            $factor = max(1, (int) ($line->conversion_factor ?: 1));
            ProductStockBatch::create([
                'product_id' => $line->product_id,
                'variant_id' => $line->variant_id,
                'initial_quantity' => $baseQuantity,
                'remaining_quantity' => $baseQuantity,
                'cost_price' => round((float) $line->unit_cost / $factor, 2),
                'sell_price' => 0,
                'imported_at' => now()->toDateString(),
                'is_opening_balance' => true,
            ]);
            return;
        }
        foreach ($line->stockBatchAllocations as $allocation) {
            ProductStockBatch::query()->whereKey($allocation->product_stock_batch_id)->lockForUpdate()->increment('remaining_quantity', $allocation->quantity);
        }
        $line->stockBatchAllocations()->delete();
    }
}
