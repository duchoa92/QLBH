<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Đối soát tồn kho hàng thường giữa các nơi lưu: cột tồn (products.stock / product_variants.stock)
 * và các lô FIFO (product_stock_batches), cùng tổng biến thể so với tồn sản phẩm.
 * Hàng IMEI đối soát riêng bằng inventory:sync-imei-stock.
 */
class AuditStockBatches extends Command
{
    protected $signature = 'stock:audit-batches
        {--fix : Áp dụng sửa (mặc định chỉ liệt kê)}
        {--trust=none : Khi tồn các lô LỚN HƠN cột tồn: stock = tin cột tồn (giảm lô cũ nhất), batches = tin lô (đặt lại cột tồn), none = chỉ báo}';

    protected $description = 'Đối soát tồn kho hai chiều giữa cột tồn, lô FIFO và tổng biến thể; --fix để sửa';

    public function handle(): int
    {
        $trust = (string) $this->option('trust');
        if (! in_array($trust, ['none', 'stock', 'batches'], true)) {
            $this->error('--trust chỉ nhận: none, stock, batches.');

            return self::INVALID;
        }

        $missingBatch = [];   // cột tồn > tổng lô: phần tồn chưa thuộc lô nào
        $excessBatch = [];    // tổng lô > cột tồn: lô còn hàng nhưng cột tồn đã trừ
        $variantMismatch = []; // tồn sản phẩm != tổng tồn biến thể

        Product::query()
            ->where('manage_stock_by_serial', false)
            ->where(fn ($query) => $query->whereNull('product_type')->orWhere('product_type', '!=', 'imei'))
            ->with('variants:id,product_id,stock,cost_price,sell_price,is_active')
            ->chunkById(200, function ($products) use (&$missingBatch, &$excessBatch, &$variantMismatch): void {
                foreach ($products as $product) {
                    $activeVariants = $product->variants->where('is_active', true)->values();
                    $targets = $activeVariants->isNotEmpty() ? $activeVariants->all() : [null];

                    foreach ($targets as $variant) {
                        $holder = $variant ?? $product;
                        $batchTotal = (int) ProductStockBatch::query()
                            ->where('product_id', $product->id)
                            ->when($variant, fn ($query) => $query->where('variant_id', $variant->id), fn ($query) => $query->whereNull('variant_id'))
                            ->sum('remaining_quantity');
                        $gap = (int) $holder->stock - $batchTotal;
                        $row = ['product' => $product, 'variant' => $variant, 'holder' => $holder, 'stock' => (int) $holder->stock, 'batches' => $batchTotal, 'gap' => $gap];

                        if ($gap > 0) {
                            $missingBatch[] = $row;
                        } elseif ($gap < 0 && $batchTotal > 0) {
                            $excessBatch[] = $row;
                        }
                    }

                    if ($activeVariants->isNotEmpty() && (int) $product->stock !== (int) $activeVariants->sum('stock')) {
                        $variantMismatch[] = ['product' => $product, 'stock' => (int) $product->stock, 'variants' => (int) $activeVariants->sum('stock')];
                    }
                }
            });

        if ($missingBatch === [] && $excessBatch === [] && $variantMismatch === []) {
            $this->info('Tồn kho khớp: cột tồn, lô FIFO và tổng biến thể không chênh lệch.');

            return self::SUCCESS;
        }

        $this->report('Tồn chưa thuộc lô nào (cột tồn > tổng lô)', $missingBatch);
        $this->report('Lô còn hàng nhiều hơn cột tồn (tổng lô > cột tồn)', $excessBatch);

        if ($variantMismatch !== []) {
            $this->warn('Tồn sản phẩm khác tổng tồn các biến thể:');
            $this->table(['ID', 'Sản phẩm', 'Tồn sản phẩm', 'Tổng biến thể'], collect($variantMismatch)->map(fn (array $row) => [
                $row['product']->id, $row['product']->name, $row['stock'], $row['variants'],
            ])->all());
        }

        if (! $this->option('fix')) {
            $this->warn('Chạy lại với --fix để sửa. Lô > cột tồn cần thêm --trust=stock hoặc --trust=batches (xem --help); mặc định không đụng vào.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($missingBatch, $excessBatch, $trust): void {
            // 1) Tạo lô đầu kỳ cho phần tồn chưa thuộc lô nào (không đổi cột tồn).
            foreach ($missingBatch as $row) {
                ProductStockBatch::create([
                    'product_id' => $row['product']->id,
                    'variant_id' => $row['variant']?->id,
                    'initial_quantity' => $row['gap'],
                    'remaining_quantity' => $row['gap'],
                    'cost_price' => round((float) ($row['holder']->cost_price ?? 0), 2),
                    'sell_price' => (float) ($row['holder']->sell_price ?? 0),
                    'imported_at' => ($row['product']->created_at ?? now())->toDateString(),
                    'is_opening_balance' => true,
                ]);
            }

            // 2) Lô > cột tồn: chỉ sửa khi người chạy chọn nguồn đáng tin.
            if ($trust !== 'none') {
                foreach ($excessBatch as $row) {
                    $excess = -$row['gap'];
                    if ($trust === 'stock') {
                        $this->shrinkOldestBatches((int) $row['product']->id, $row['variant']?->id, $excess);
                    } else {
                        $row['holder']->forceFill(['stock' => $row['batches']])->save();
                    }
                }
            }

            // 3) Tồn sản phẩm có biến thể luôn bằng tổng biến thể (sau khi biến thể đã chốt).
            $productIds = collect($excessBatch)->merge($missingBatch)->pluck('product.id')->unique();
            Product::query()->whereKey($productIds)->with('variants')->get()->each(function (Product $product): void {
                $active = $product->variants->where('is_active', true);
                if ($active->isNotEmpty()) {
                    $product->forceFill(['stock' => (int) ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->sum('stock')])->save();
                }
            });
        });

        // Chênh lệch tổng biến thể không thuộc nhóm trên: biến thể là dữ liệu gốc, đặt lại tồn sản phẩm.
        foreach ($variantMismatch as $row) {
            $product = Product::query()->find($row['product']->id);
            $product?->forceFill(['stock' => (int) ProductVariant::query()->where('product_id', $product->id)->where('is_active', true)->sum('stock')])->save();
        }

        $this->info('Đã sửa: ' . count($missingBatch) . ' lô đầu kỳ tạo mới'
            . ($trust !== 'none' ? ', ' . count($excessBatch) . " nhóm lô > tồn xử lý theo --trust={$trust}" : '')
            . ', ' . count($variantMismatch) . ' sản phẩm đặt lại tồn theo biến thể.');

        return self::SUCCESS;
    }

    private function report(string $title, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->warn($title . ':');
        $this->table(
            ['ID', 'Sản phẩm', 'Biến thể', 'Cột tồn', 'Tổng các lô', 'Chênh lệch'],
            collect($rows)->map(fn (array $row) => [
                $row['product']->id, $row['product']->name, $row['variant']?->id ?? '—', $row['stock'], $row['batches'], $row['gap'],
            ])->all()
        );
    }

    /** Giảm lô cũ nhất trước (đúng thứ tự FIFO) cho phần lô còn hàng nhưng thực tế đã xuất khỏi kho. */
    private function shrinkOldestBatches(int $productId, ?int $variantId, int $excess): void
    {
        $batches = ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->where('remaining_quantity', '>', 0)
            ->orderBy('imported_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($excess <= 0) {
                break;
            }
            $take = min($excess, (int) $batch->remaining_quantity);
            $batch->decrement('remaining_quantity', $take);
            $excess -= $take;
        }
    }
}
