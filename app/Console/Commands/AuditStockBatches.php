<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductStockBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditStockBatches extends Command
{
    protected $signature = 'stock:audit-batches {--fix : Tạo lô đầu kỳ cho phần tồn chưa thuộc lô nào}';

    protected $description = 'Liệt kê sản phẩm có tồn kho lớn hơn tổng tồn các lô (tồn chưa thuộc lô nào); dùng --fix để tạo lô đầu kỳ';

    public function handle(): int
    {
        $found = [];

        Product::query()
            ->where('manage_stock_by_serial', false)
            ->where(fn ($query) => $query->whereNull('product_type')->orWhere('product_type', '!=', 'imei'))
            ->with('variants:id,product_id,stock,cost_price,sell_price')
            ->chunkById(200, function ($products) use (&$found): void {
                foreach ($products as $product) {
                    $targets = $product->variants->isNotEmpty() ? $product->variants->all() : [null];

                    foreach ($targets as $variant) {
                        $holder = $variant ?? $product;
                        $batchTotal = (int) ProductStockBatch::query()
                            ->where('product_id', $product->id)
                            ->when($variant, fn ($query) => $query->where('variant_id', $variant->id), fn ($query) => $query->whereNull('variant_id'))
                            ->sum('remaining_quantity');
                        $gap = (int) $holder->stock - $batchTotal;

                        if ($gap > 0) {
                            $found[] = ['product' => $product, 'variant' => $variant, 'holder' => $holder, 'stock' => (int) $holder->stock, 'batches' => $batchTotal, 'gap' => $gap];
                        }
                    }
                }
            });

        if ($found === []) {
            $this->info('Không có sản phẩm nào có tồn chưa thuộc lô.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Sản phẩm', 'Biến thể', 'Tồn kho', 'Tổng các lô', 'Chênh lệch'],
            collect($found)->map(fn (array $row) => [
                $row['product']->id, $row['product']->name, $row['variant']?->id ?? '—', $row['stock'], $row['batches'], $row['gap'],
            ])->all()
        );

        if (! $this->option('fix')) {
            $this->warn('Chạy lại với --fix để tạo lô đầu kỳ cho phần chênh lệch (không làm thay đổi cột tồn kho).');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($found): void {
            foreach ($found as $row) {
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
        });

        $this->info('Đã tạo ' . count($found) . ' lô đầu kỳ.');

        return self::SUCCESS;
    }
}
