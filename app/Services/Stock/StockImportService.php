<?php

namespace App\Services\Stock;

use App\Models\StockImport;
use App\Models\Supplier;
use App\Models\SupplierDebt;
use App\Models\StockImportItem;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductImeiHistory;
use App\Models\ProductStockBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
            $paidAmount = ! empty($data['supplier_id'])
                ? min($grandTotal, max(0, (float) ($data['paid_amount'] ?? 0)))
                : 0;
            $oldDebtPaid = ! empty($data['supplier_id'])
                ? max(0, (float) ($data['old_debt_paid'] ?? 0))
                : 0;
            $supplier = ! empty($data['supplier_id'])
                ? Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id'])
                : null;

            if ($supplier && $oldDebtPaid > (float) $supplier->debt_balance) {
                throw ValidationException::withMessages([
                    'old_debt_paid' => 'Số tiền trả nợ cũ vượt quá công nợ hiện tại của nhà cung cấp. Vui lòng tải lại phiếu và thử lại.',
                ]);
            }


            /*
             * ==========================================================
             * TẠO PHIẾU NHẬP
             * ==========================================================
             */

            $import = StockImport::create([
                'code' => 'IMP-' . now()->format('YmdHis'),

                'supplier_id' => $data['supplier_id'],
                'party_type' => $data['party_type'] ?? 'supplier',
                'customer_id' => $data['customer_id'] ?? null,
                'party_name' => $data['party_name'] ?? null,
                'party_phone' => $data['party_phone'] ?? null,
                'party_cccd' => $data['party_cccd'] ?? null,
                'party_address' => $data['party_address'] ?? null,

                'user_id' => auth()->id(),

                'import_date' => $data['import_date'],

                'discount' => $discount,

                'extra_fee' => $extraFee,

                'total_amount' => $totalAmount,

                'grand_total' => $grandTotal,

                'paid_amount' => $paidAmount,

                'old_debt_paid' => $oldDebtPaid,

                'payment_method' => $data['payment_method'] ?? null,

                'payment_reference' => $data['payment_reference'] ?? null,

                'note' => $data['note'] ?? null,

                'status' => 'completed',
            ]);

            if (!empty($data['supplier_id'])) {
                if ($grandTotal > 0) {
                    SupplierDebt::query()->create([
                        'supplier_id' => $data['supplier_id'],
                        'type' => 'increase',
                        'amount' => $grandTotal,
                        'source_type' => StockImport::class,
                        'source_id' => $import->id,
                        'note' => 'Công nợ phát sinh từ phiếu nhập ' . $import->code,
                        'user_id' => auth()->id(),
                    ]);
                    Supplier::query()->whereKey($data['supplier_id'])->increment('debt_balance', $grandTotal);
                }

                if ($paidAmount > 0) {
                    SupplierDebt::query()->create([
                        'supplier_id' => $data['supplier_id'],
                        'type' => 'decrease',
                        'amount' => $paidAmount,
                        'payment_method' => $data['payment_method'] ?? 'cash',
                        'source_type' => StockImport::class,
                        'source_id' => $import->id,
                        'note' => 'Thanh toán khi tạo phiếu nhập ' . $import->code,
                        'user_id' => auth()->id(),
                    ]);
                    Supplier::query()->whereKey($data['supplier_id'])->decrement('debt_balance', $paidAmount);
                }

                if ($oldDebtPaid > 0) {
                    SupplierDebt::query()->create([
                        'supplier_id' => $data['supplier_id'],
                        'type' => 'decrease',
                        'amount' => $oldDebtPaid,
                        'payment_method' => $data['payment_method'] ?? 'bank',
                        'source_type' => StockImport::class,
                        'source_id' => $import->id,
                        'note' => 'Thanh toán nợ cũ khi tạo phiếu nhập ' . $import->code
                            . (filled($data['payment_reference'] ?? null) ? ' (' . $data['payment_reference'] . ')' : ''),
                        'user_id' => auth()->id(),
                    ]);
                    Supplier::query()->whereKey($data['supplier_id'])->decrement('debt_balance', $oldDebtPaid);
                }
            }


            /*
             * ==========================================================
             * CHI TIẾT PHIẾU NHẬP
             * ==========================================================
             */

            foreach ($data['items'] as $item) {

                $productId = (int) $item['product_id'];
                $product = Product::with([
                    'unit:id,name,short_name',
                    'conversionUnit:id,name,short_name',
                ])
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
                $conversionFactor = $product->inventoryConversionFactor();
                $baseQuantity = $quantity * $conversionFactor;

                $costPrice = (float) $item['cost_price'];
                $sellPrice = array_key_exists('sell_price', $item) && $item['sell_price'] !== null
                    ? round((float) $item['sell_price'] / $conversionFactor, 2)
                    : null;
                $imeis = $this->normalizeImeis($item['imeis'] ?? [], $data['import_date'] ?? null);

                if ($product->manage_stock_by_serial && $conversionFactor > 1) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm IMEI {$product->name} không hỗ trợ quy đổi đơn vị.",
                    ]);
                }

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

                $existedInStockImeis = ProductImei::query()
                    ->whereIn('imei', collect($imeis)->pluck('imei'))
                    ->where('status', ProductImei::STATUS_IN_STOCK)
                    ->pluck('imei');

                if ($existedInStockImeis->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'IMEI đang còn trong kho, không thể nhập trùng: ' . $existedInStockImeis->join(', '),
                    ]);
                }


                /*
                 * ------------------------------------------------------
                 * TẠO CHI TIẾT
                 * ------------------------------------------------------
                 */

                $importItem = StockImportItem::create([
                    'stock_import_id' => $import->id,

                    'product_id' => $productId,

                    'variant_id' => $variantId,

                    'unit_id' => $product->has_unit_conversion
                        ? $product->conversion_unit_id
                        : $product->unit_id,

                    'unit_name' => $product->has_unit_conversion
                        ? ($product->conversionUnit?->short_name ?: $product->conversionUnit?->name)
                        : ($product->unit?->short_name ?: $product->unit?->name),

                    'quantity' => $quantity,

                    'base_quantity' => $baseQuantity,

                    'conversion_factor' => $conversionFactor,

                    'cost_price' => $costPrice,

                    'has_vat' => (bool) ($item['has_vat'] ?? false),

                    'supplier_warranty_value' => $item['supplier_warranty_value'] ?? null,

                    'supplier_warranty_unit' => ! empty($item['supplier_warranty_value'])
                        ? ($item['supplier_warranty_unit'] ?? 'months')
                        : null,

                    'extra_info' => $item['extra_info'] ?? null,
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
                            $baseQuantity
                        );

                } else {

                    Product::whereKey($productId)
                        ->increment(
                            'stock',
                            $baseQuantity
                        );
                }

                // Sản phẩm thường/biến thể luôn giữ giá theo từng lô nhập.
                // Giá bán mặc định của sản phẩm không bị ghi đè khi nhập hàng.
                if (! $product->manage_stock_by_serial) {
                    ProductStockBatch::create([
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'stock_import_item_id' => $importItem->id,
                        'initial_quantity' => $baseQuantity,
                        'remaining_quantity' => $baseQuantity,
                        'cost_price' => round($costPrice / $conversionFactor, 2),
                        'sell_price' => $sellPrice ?? 0,
                        'imported_at' => $import->import_date,
                    ]);

                    // Chỉ dùng làm gợi ý cho lần nhập kế tiếp; giá vốn thực tế khi
                    // bán luôn lấy từ lô FIFO, không lấy từ hai cột này.
                    if ($variantId !== null) {
                        ProductVariant::whereKey($variantId)->update(['cost_price' => round($costPrice / $conversionFactor, 2)]);
                    } else {
                        Product::whereKey($productId)->update(['cost_price' => round($costPrice / $conversionFactor, 2)]);
                    }

                }


                /*
                 * ------------------------------------------------------
                 * LƯU IMEI
                 * ------------------------------------------------------
                 */

                foreach ($imeis as $imeiData) {
                    $imei = ProductImei::query()
                        ->where('imei', $imeiData['imei'])
                        ->first();

                    $imeiPayload = [
                        'product_id' => $productId,
                        'variant_id' => $imeiData['variant_id'] ?? $variantId,
                        'imei' => $imeiData['imei'],
                        'cost_price' => $imeiData['cost_price'] ?? $costPrice,
                        'sell_price' => $imeiData['sell_price'] ?? 0,
                        'extra_info' => $imeiData['extra_info'] ?? null,
                        'warranty_expired_at' => $imeiData['warranty_expired_at'] ?? null,
                        'status' => ProductImei::STATUS_IN_STOCK,
                        'sold_at' => null,
                        'imported_at' => now(),
                    ];

                    if ($imei) {
                        $imei->update($imeiPayload);
                    } else {
                        $imei = ProductImei::create($imeiPayload);
                    }

                    ProductImeiHistory::create([
                        'product_imei_id' => $imei->id,
                        'imei' => $imei->imei,
                        'type' => 'import',
                        'reference_type' => StockImport::class,
                        'reference_id' => $import->id,
                        'cost_price' => $imeiPayload['cost_price'],
                        'sell_price' => $imeiPayload['sell_price'],
                        'meta' => [
                            'product_id' => $productId,
                            'variant_id' => $imeiPayload['variant_id'],
                            'supplier_id' => $data['supplier_id'] ?? null,
                            'import_code' => $import->code,
                            'extra_info' => $imeiPayload['extra_info'],
                            'warranty_expired_at' => $imeiPayload['warranty_expired_at'],
                        ],
                        'happened_at' => $import->import_date,
                    ]);
                }

                if ($product->manage_stock_by_serial) {
                    app(ImeiStockSynchronizer::class)->sync($productId);
                }
            }

            return $import;
        });
    }

    private function normalizeImeis(array $rawImeis, ?string $importDate = null): array
    {
        return collect($rawImeis)
            ->map(function ($imeiData) use ($importDate) {
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
                    'extra_info' => is_array($imeiData)
                        ? $this->normalizeImeiExtraInfo($imeiData)
                        : null,
                    'warranty_expired_at' => is_array($imeiData)
                        ? $this->calculateSupplierWarrantyExpiredAt($imeiData, $importDate)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeImeiExtraInfo(array $imeiData): ?array
    {
        $extraInfo = is_array($imeiData['extra_info'] ?? null)
            ? $imeiData['extra_info']
            : [];

        $imagePath = null;

        if (($imeiData['info_image'] ?? null) instanceof UploadedFile) {
            $imagePath = $imeiData['info_image']->store('product-imei-info', 'public');
        }

        $normalized = collect([
            'note' => $extraInfo['note'] ?? null,
            'is_used' => $extraInfo['is_used'] ?? null,
            'account' => $extraInfo['account'] ?? null,
            'password' => $extraInfo['password'] ?? null,
            'customer_warranty_duration_value' => $extraInfo['customer_warranty_duration_value'] ?? null,
            'customer_warranty_duration_unit' => $extraInfo['customer_warranty_duration_unit'] ?? null,
            'image_path' => $imagePath,
            'supplier_warranty_value' => $imeiData['warranty_duration_value'] ?? null,
            'supplier_warranty_unit' => $imeiData['warranty_duration_unit'] ?? null,
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->all();

        return $normalized === [] ? null : $normalized;
    }

    private function calculateSupplierWarrantyExpiredAt(array $imeiData, ?string $importDate = null): ?Carbon
    {
        $value = (int) ($imeiData['warranty_duration_value'] ?? 0);
        $unit = $imeiData['warranty_duration_unit'] ?? null;

        if ($value <= 0 || ! in_array($unit, ['days', 'months'], true)) {
            return null;
        }

        $date = $importDate
            ? Carbon::parse($importDate)->startOfDay()
            : now()->startOfDay();

        return $unit === 'months'
            ? $date->addMonths($value)
            : $date->addDays($value);
    }
}
