<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ImeiStockSynchronizer
{
    public function sync(Product|int $product): void
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $countsByVariant = ProductImei::query()
            ->where('product_id', $productId)
            ->where('status', ProductImei::STATUS_IN_STOCK)
            ->select('variant_id', DB::raw('COUNT(*) as imei_count'))
            ->groupBy('variant_id')
            ->pluck('imei_count', 'variant_id');

        Product::query()
            ->whereKey($productId)
            ->update(['stock' => (int) $countsByVariant->sum()]);

        ProductVariant::query()
            ->where('product_id', $productId)
            ->update(['stock' => 0]);

        foreach ($countsByVariant as $variantId => $count) {
            if ($variantId !== null) {
                ProductVariant::query()
                    ->where('product_id', $productId)
                    ->whereKey($variantId)
                    ->update(['stock' => (int) $count]);
            }
        }
    }
}
