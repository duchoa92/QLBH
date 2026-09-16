<?php

namespace App\Services\Stock;

use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class StockImportService
{
    public function import(array $data)
    {
        return DB::transaction(function () use ($data) {

            /*
             * ==========================================================
             * TÍNH TIỀN
             * ==========================================================
             */

            $totalAmount = collect($data['items'])
                ->sum(function (array $item): float {
                    return (float) ($item['quantity'] ?? 0)
                        * (float) ($item['cost_price'] ?? 0);
                });

            $discount = (float) ($data['discount'] ?? 0);
            $extraFee = (float) ($data['extra_fee'] ?? 0);

            $grandTotal = max(
                0,
                $totalAmount - $discount + $extraFee
            );


            /*
             * ==========================================================
             * TẠO PHIẾU NHẬP
             * ==========================================================
             */

            $import = StockImport::create([
                'code' => 'IMP-' . now()->format('YmdHis'),

                'supplier_id' => $data['supplier_id'],

                'user_id' => auth()->id(),

                'import_date' => $data['import_date'],

                'discount' => $discount,

                'extra_fee' => $extraFee,

                'total_amount' => $totalAmount,

                'grand_total' => $grandTotal,

                'note' => $data['note'] ?? null,

                'status' => 'completed',
            ]);


            /*
             * ==========================================================
             * CHI TIẾT PHIẾU NHẬP
             * ==========================================================
             */

            foreach ($data['items'] as $item) {

                $productId = (int) $item['product_id'];
                $product = Product::with('unit:id,name,short_name')
                    ->findOrFail($productId);

                $variantId = !empty($item['variant_id'])
                    ? (int) $item['variant_id']
                    : null;

                if ($variantId !== null) {
                    $variantExists = ProductVariant::query()
                        ->whereKey($variantId)
                        ->where('product_id', $productId)
                        ->where('is_active', true)
                        ->exists();

                    if (! $variantExists) {
                        throw ValidationException::withMessages([
                            'items' => "Biến thể của sản phẩm {$product->name} không còn hoạt động.",
                        ]);
                    }
                }

                $quantity = (int) round((float) $item['quantity']);

                $costPrice = (float) $item['cost_price'];
                $imeis = $this->normalizeImeis($item['imeis'] ?? []);

                if ($product->manage_stock_by_serial && count($imeis) !== $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm {$product->name} cần số IMEI khớp số lượng nhập.",
                    ]);
                }

                $duplicatedImeis = collect($imeis)
                    ->pluck('imei')
                    ->duplicates()
                    ->values();

                if ($duplicatedImeis->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'IMEI bị trùng trong phiếu nhập: ' . $duplicatedImeis->join(', '),
                    ]);
                }

                $existedImeis = ProductImei::query()
                    ->whereIn('imei', collect($imeis)->pluck('imei'))
                    ->pluck('imei');

                if ($existedImeis->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'IMEI đã tồn tại trong hệ thống: ' . $existedImeis->join(', '),
                    ]);
                }


                /*
                 * ------------------------------------------------------
                 * TẠO CHI TIẾT
                 * ------------------------------------------------------
                 */

                StockImportItem::create([
                    'stock_import_id' => $import->id,

                    'product_id' => $productId,

                    'variant_id' => $variantId,

                    'unit_id' => $item['unit_id'] ?? $product->unit_id,

                    'unit_name' => $item['unit_name'] ?? ($product->unit?->short_name ?: $product->unit?->name),

                    'quantity' => $quantity,

                    'cost_price' => $costPrice,
                ]);


                /*
                 * ------------------------------------------------------
                 * CỘNG TỒN KHO
                 * ------------------------------------------------------
                 */

                if ($variantId !== null) {

                    ProductVariant::whereKey($variantId)
                        ->increment(
                            'stock',
                            $quantity
                        );

                } else {

                    Product::whereKey($productId)
                        ->increment(
                            'stock',
                            $quantity
                        );
                }


                /*
                 * ------------------------------------------------------
                 * LƯU IMEI
                 * ------------------------------------------------------
                 */

                foreach ($imeis as $imeiData) {
                    ProductImei::create([
                        'product_id' => $productId,

                        'variant_id' => $imeiData['variant_id'] ?? $variantId,

                        'imei' => $imeiData['imei'],

                        'cost_price' => $imeiData['cost_price'] ?? $costPrice,

                        'sell_price' => $imeiData['sell_price'] ?? 0,

                        'status' => ProductImei::STATUS_IN_STOCK,
                    ]);
                }
            }

            return $import;
        });
    }

    private function normalizeImeis(array $rawImeis): array
    {
        return collect($rawImeis)
            ->map(function ($imeiData) {
                $imei = is_array($imeiData)
                    ? ($imeiData['imei'] ?? null)
                    : $imeiData;

                $imei = trim((string) $imei);

                if ($imei === '') {
                    return null;
                }

                return [
                    'imei' => $imei,
                    'variant_id' => is_array($imeiData) && !empty($imeiData['variant_id'])
                        ? (int) $imeiData['variant_id']
                        : null,
                    'cost_price' => is_array($imeiData)
                        ? (float) ($imeiData['cost_price'] ?? 0)
                        : null,
                    'sell_price' => is_array($imeiData)
                        ? (float) ($imeiData['sell_price'] ?? 0)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
