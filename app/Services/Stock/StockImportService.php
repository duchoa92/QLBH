<?php
namespace App\Services\Stock;

use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Support\Facades\DB;

class StockImportService
{
    public function import(array $data)
    {
        return DB::transaction(function () use ($data) {

            $import = StockImport::create([
                'code' => 'IMP-' . time(),
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {

                // lưu chi tiết
                StockImportItem::create([
                    'stock_import_id' => $import->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                ]);

                // ========================
                // 1. CỘNG TỒN KHO
                // ========================
                if ($item['variant_id']) {
                    ProductVariant::where('id', $item['variant_id'])
                        ->increment('stock', $item['quantity']);
                } else {
                    Product::where('id', $item['product_id'])
                        ->increment('stock', $item['quantity']);
                }

                // ========================
                // 2. XỬ LÝ IMEI
                // ========================
                if (!empty($item['imeis'])) {

                    foreach ($item['imeis'] as $imei) {

                        ProductImei::create([
                            'product_id' => $item['product_id'],
                            'variant_id' => $item['variant_id'],
                            'imei' => $imei,
                            'status' => 'in_stock'
                        ]);
                    }
                }
            }

            return $import;
        });
    }
}