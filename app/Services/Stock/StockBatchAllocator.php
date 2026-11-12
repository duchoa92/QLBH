<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\RepairPart;
use App\Models\SaleItem;
use App\Models\SaleItemGift;
use Illuminate\Validation\ValidationException;

class StockBatchAllocator
{
    /** @return array{total_cost: float, allocated_quantity: int} */
    public function allocate(SaleItem|SaleItemGift|RepairPart $line, int $productId, ?int $variantId, int $quantity): array
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

    /** Đã từng có lô cho sản phẩm/biến thể này (kể cả lô đã hết hàng) — nghĩa là phải kiểm tồn theo lô. */
    public function hasAnyBatch(int $productId, ?int $variantId): bool
    {
        return ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->exists();
    }

    /** Tổng tồn còn lại trong các lô của sản phẩm/biến thể. */
    public function availableQuantity(int $productId, ?int $variantId): int
    {
        return (int) ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->sum('remaining_quantity');
    }

    /**
     * Tạo lô đầu kỳ cho phần tồn (cột stock) chưa thuộc lô nào, để tồn theo lô và tồn tổng
     * không lệch nhau trước khi hoàn/cộng thêm hàng vào lô.
     */
    public function ensureCoverage(int $productId, ?int $variantId): void
    {
        $product = Product::query()->lockForUpdate()->find($productId);
        if (! $product || $product->manage_stock_by_serial || $product->product_type === 'imei') {
            return;
        }

        // Sản phẩm có biến thể: tồn nằm ở từng biến thể, không có lô cấp sản phẩm.
        if ($variantId === null && $product->variants()->where('is_active', true)->exists()) {
            return;
        }

        $holder = $variantId
            ? ProductVariant::query()->lockForUpdate()->where('product_id', $productId)->find($variantId)
            : $product;
        if (! $holder) {
            return;
        }

        $gap = (int) $holder->stock - $this->availableQuantity($productId, $variantId);
        if ($gap <= 0) {
            return;
        }

        ProductStockBatch::create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'initial_quantity' => $gap,
            'remaining_quantity' => $gap,
            'cost_price' => round((float) ($holder->cost_price ?? 0), 2),
            'sell_price' => (float) ($holder->sell_price ?? 0),
            'imported_at' => ($product->created_at ?? now())->toDateString(),
            'is_opening_balance' => true,
        ]);
    }

    public function restore(SaleItem|SaleItemGift|RepairPart $line): void
    {
        $line->loadMissing('stockBatchAllocations');
        if ($line->stockBatchAllocations->isEmpty()) {
            // Linh kiện sửa chữa xuất trước khi có phân bổ lô chỉ trừ cột tồn kho, nên khi hoàn
            // chỉ cần cộng lại cột tồn kho (luồng gọi tự làm), không được tạo thêm lô.
            if ($line instanceof RepairPart) {
                return;
            }

            // Hóa đơn tạo trước khi bật quản lý theo lô không có lịch sử phân bổ.
            // Khi hủy/sửa, tạo lô phục hồi để số tồn tổng và tồn theo lô không lệch.
            // Phần tồn hiện có chưa thuộc lô nào phải được phủ bằng lô đầu kỳ trước, nếu không
            // sản phẩm sẽ chỉ còn một lô nhỏ và phần tồn cũ không bán được theo lô.
            $this->ensureCoverage((int) $line->product_id, $line->variant_id ? (int) $line->variant_id : null);

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
