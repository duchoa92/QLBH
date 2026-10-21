<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Stock\ImeiStockSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncImeiStock extends Command
{
    protected $signature = 'inventory:sync-imei-stock {--dry-run : Only report products with stock mismatches}';

    protected $description = 'Reconcile IMEI product and variant stock with in-stock IMEI records';

    public function handle(ImeiStockSynchronizer $synchronizer): int
    {
        $checked = 0;
        $mismatched = 0;

        Product::query()
            ->where(function ($query): void {
                $query->where('manage_stock_by_serial', true)
                    ->orWhere('product_type', 'imei');
            })
            ->with('variants:id,product_id,stock')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($synchronizer, &$checked, &$mismatched): void {
                foreach ($products as $product) {
                    $imeiCount = $product->imeis()
                        ->where('status', 'in_stock')
                        ->count();
                    $variantCounts = $product->imeis()
                        ->where('status', 'in_stock')
                        ->select('variant_id', DB::raw('COUNT(*) as imei_count'))
                        ->groupBy('variant_id')
                        ->pluck('imei_count', 'variant_id');

                    $hasMismatch = (int) $product->stock !== (int) $imeiCount;
                    foreach ($product->variants as $variant) {
                        if ((int) $variant->stock !== (int) ($variantCounts->get($variant->id) ?? 0)) {
                            $hasMismatch = true;
                            break;
                        }
                    }

                    $checked++;
                    if (! $hasMismatch) {
                        continue;
                    }

                    $mismatched++;
                    if (! $this->option('dry-run')) {
                        DB::transaction(fn () => $synchronizer->sync($product));
                    }
                }
            });

        $this->info("Đã kiểm tra {$checked} sản phẩm IMEI; phát hiện {$mismatched} sản phẩm lệch tồn.");
        if ($this->option('dry-run')) {
            $this->comment('Chế độ xem trước: không có dữ liệu nào được thay đổi.');
        } else {
            $this->info('Đã đồng bộ tồn sản phẩm và biến thể theo IMEI còn trong kho.');
        }

        return self::SUCCESS;
    }
}
