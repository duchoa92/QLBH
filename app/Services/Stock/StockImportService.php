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
use App\Models\Repair;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class StockImportService
{
    public function replace(StockImport $stockImport, array $data): StockImport
    {
        return DB::transaction(function () use ($stockImport, $data): StockImport {
            $target = StockImport::query()->lockForUpdate()->findOrFail($stockImport->id);
            $originalCode = $target->code;
            $originalUserId = $target->user_id;

            // Hoàn tác tồn/công nợ trong transaction để áp dữ liệu mới, nhưng
            // tuyệt đối không gọi cancel(): sửa phiếu không được đổi trạng thái
            // sang cancelled hay để lại ghi chú hủy.
            $this->reverseForUpdate($target);
            $replacement = $this->import($data);

            StockImportItem::query()
                ->where('stock_import_id', $replacement->id)
                ->update(['stock_import_id' => $target->id]);
            ProductImeiHistory::query()
                ->where('reference_type', StockImport::class)
                ->where('reference_id', $replacement->id)
                ->update(['reference_id' => $target->id]);
            SupplierDebt::query()
                ->where('source_type', StockImport::class)
                ->where('source_id', $replacement->id)
                ->update(['source_id' => $target->id]);

            $attributes = collect($replacement->getAttributes())
                ->except(['id', 'code', 'user_id', 'created_at', 'updated_at'])
                ->all();
            $target->forceFill([
                ...$attributes,
                'code' => $originalCode,
                'user_id' => $originalUserId,
                'status' => 'completed',
            ])->save();

            $replacement->delete();

            return $target->fresh();
        });
    }

    /** Hoàn tác dữ liệu vận hành của phiếu để ghi đè tại chỗ khi sửa. */
    private function reverseForUpdate(StockImport $stockImport): void
    {
        $items = StockImportItem::query()
            ->where('stock_import_id', $stockImport->id)
            ->lockForUpdate()
            ->get();
        $itemIds = $items->pluck('id');

        $this->assertNoLaterSupplierActivity($stockImport, 'sửa');

        $batches = ProductStockBatch::query()
            ->whereIn('stock_import_item_id', $itemIds)
            ->lockForUpdate()
            ->get();
        if ($batches->contains(fn (ProductStockBatch $batch) => (int) $batch->remaining_quantity !== (int) $batch->initial_quantity)) {
            throw ValidationException::withMessages(['import' => 'Không thể sửa phiếu vì một phần hàng trong lô đã được bán.']);
        }

        $histories = ProductImeiHistory::query()
            ->where('reference_type', StockImport::class)
            ->where('reference_id', $stockImport->id)
            ->where('type', 'import')
            ->with('productImei')
            ->lockForUpdate()
            ->get();
        if ($histories->contains(fn (ProductImeiHistory $history) => $history->productImei?->status !== ProductImei::STATUS_IN_STOCK)) {
            throw ValidationException::withMessages(['import' => 'Không thể sửa phiếu vì có IMEI đã bán hoặc không còn trong kho.']);
        }

        $this->reverseImportedStock($items);

        $histories->each(fn (ProductImeiHistory $history) => $history->productImei?->delete());
        ProductImeiHistory::query()->whereKey($histories->pluck('id'))->delete();
        ProductStockBatch::query()->whereIn('id', $batches->pluck('id'))->delete();

        if ($stockImport->supplier_id) {
            $supplier = Supplier::query()->lockForUpdate()->find($stockImport->supplier_id);
            if ($supplier) {
                $netDebt = (float) $stockImport->grand_total - (float) $stockImport->paid_amount - (float) $stockImport->old_debt_paid;
                $supplier->decrement('debt_balance', $netDebt);
            }
            collect($stockImport->old_debt_allocations ?? [])->each(function (array $allocation): void {
                $amount = max(0, (float) ($allocation['amount'] ?? 0));
                $oldImportId = (int) ($allocation['stock_import_id'] ?? 0);
                if ($oldImportId && $amount > 0) StockImport::query()->whereKey($oldImportId)->decrement('paid_amount', $amount);
            });
            SupplierDebt::query()
                ->where('source_type', StockImport::class)
                ->where('source_id', $stockImport->id)
                ->delete();
        }

        StockImportItem::query()->whereIn('id', $itemIds)->delete();
        $items->pluck('product_id')->unique()->each(function (int $productId): void {
            if (Product::query()->find($productId)?->manage_stock_by_serial) app(ImeiStockSynchronizer::class)->sync($productId);
        });
    }

    public function cancel(StockImport $stockImport, ?string $reason = null): StockImport
    {
        return DB::transaction(function () use ($stockImport, $reason): StockImport {
            $import = StockImport::query()->lockForUpdate()->findOrFail($stockImport->id);
            if ($import->status === 'cancelled') {
                return $import;
            }

            $items = StockImportItem::query()
                ->where('stock_import_id', $import->id)
                ->lockForUpdate()
                ->get();
            $itemIds = $items->pluck('id');

            $this->assertNoLaterSupplierActivity($import, 'hủy');

            $batches = ProductStockBatch::query()
                ->whereIn('stock_import_item_id', $itemIds)
                ->lockForUpdate()
                ->get();
            if ($batches->contains(fn (ProductStockBatch $batch) => (int) $batch->remaining_quantity !== (int) $batch->initial_quantity)) {
                throw ValidationException::withMessages([
                    'import' => 'Không thể hủy phiếu nhập vì một phần hàng trong lô đã được bán.',
                ]);
            }

            $histories = ProductImeiHistory::query()
                ->where('reference_type', StockImport::class)
                ->where('reference_id', $import->id)
                ->where('type', 'import')
                ->with('productImei')
                ->lockForUpdate()
                ->get();
            if ($histories->contains(fn (ProductImeiHistory $history) => $history->productImei?->status !== ProductImei::STATUS_IN_STOCK)) {
                throw ValidationException::withMessages([
                    'import' => 'Không thể hủy phiếu nhập vì có IMEI đã bán hoặc không còn trong kho.',
                ]);
            }

            $this->reverseImportedStock($items);

            $histories->each(function (ProductImeiHistory $history): void {
                $history->productImei?->delete();
            });
            ProductImeiHistory::query()->whereKey($histories->pluck('id'))->delete();
            ProductStockBatch::query()->whereIn('id', $batches->pluck('id'))->delete();

            if ($import->supplier_id) {
                $supplier = Supplier::query()->lockForUpdate()->find($import->supplier_id);
                if ($supplier) {
                    $netDebt = (float) $import->grand_total - (float) $import->paid_amount - (float) $import->old_debt_paid;
                    $supplier->decrement('debt_balance', $netDebt);
                }
                collect($import->old_debt_allocations ?? [])->each(function (array $allocation): void {
                    $amount = max(0, (float) ($allocation['amount'] ?? 0));
                    $oldImportId = (int) ($allocation['stock_import_id'] ?? 0);
                    if ($oldImportId && $amount > 0) {
                        StockImport::query()->whereKey($oldImportId)->decrement('paid_amount', $amount);
                    }
                });
                SupplierDebt::query()
                    ->where('source_type', StockImport::class)
                    ->where('source_id', $import->id)
                    ->delete();
            }

            $import->update([
                'status' => 'cancelled',
                'note' => trim(($import->note ? $import->note . "\n" : '') . 'Hủy phiếu: ' . ($reason ?: 'Không nêu lý do')),
            ]);

            $items->pluck('product_id')->unique()->each(function (int $productId): void {
                $product = Product::query()->find($productId);
                if ($product?->manage_stock_by_serial) {
                    app(ImeiStockSynchronizer::class)->sync($productId);
                }
            });

            return $import->fresh();
        });
    }

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
            // Mua lại từ khách lẻ không có công nợ: phiếu luôn được chi trả đủ (khớp cách giao diện hiển thị).
            $isCustomerBuyback = empty($data['supplier_id']) && ($data['party_type'] ?? 'supplier') === 'customer';
            $paidAmount = ! empty($data['supplier_id'])
                ? min($grandTotal, max(0, (float) ($data['paid_amount'] ?? 0)))
                : ($isCustomerBuyback ? $grandTotal : 0);
            $oldDebtPaid = ! empty($data['supplier_id'])
                ? max(0, (float) ($data['old_debt_paid'] ?? 0))
                : 0;
            $tenderedAmount = ! empty($data['supplier_id'])
                ? max(0, (float) ($data['tendered_amount'] ?? ($paidAmount + $oldDebtPaid)))
                : ($isCustomerBuyback ? $grandTotal : 0);
            $supplier = ! empty($data['supplier_id'])
                ? Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id'])
                : null;

            if ($supplier && $oldDebtPaid > (float) $supplier->debt_balance) {
                throw ValidationException::withMessages([
                    'old_debt_paid' => 'Số tiền trả nợ cũ vượt quá công nợ hiện tại của nhà cung cấp. Vui lòng tải lại phiếu và thử lại.',
                ]);
            }

            $oldDebtAllocations = collect($data['old_debt_allocations'] ?? [])
                ->map(fn (array $allocation) => [
                    'stock_import_id' => (int) ($allocation['stock_import_id'] ?? 0),
                    'amount' => round(max(0, (float) ($allocation['amount'] ?? 0)), 2),
                ])
                ->filter(fn (array $allocation) => $allocation['stock_import_id'] > 0 && $allocation['amount'] > 0)
                ->values();

            if ($oldDebtPaid > 0 && abs((float) $oldDebtAllocations->sum('amount') - $oldDebtPaid) > 0.01) {
                throw ValidationException::withMessages([
                    'old_debt_allocations' => 'Vui lòng chọn và phân bổ đầy đủ số tiền trả nợ cũ vào các phiếu nhập.',
                ]);
            }

            // Không tin giá trị tiền thừa từ trình duyệt: tính từ số tiền đã đưa
            // và đúng các khoản thực sự được áp vào phiếu mới/nợ cũ.
            $changeAmount = max(0, $tenderedAmount - $paidAmount - $oldDebtPaid);

            $oldDebtAllocations = $oldDebtAllocations->map(function (array $allocation) use ($supplier): array {
                $oldImport = StockImport::query()
                    ->lockForUpdate()
                    ->where('supplier_id', $supplier?->id)
                    ->where('status', 'completed')
                    ->find($allocation['stock_import_id']);

                if (! $oldImport) {
                    throw ValidationException::withMessages(['old_debt_allocations' => 'Phiếu nhập cần trả nợ không còn hợp lệ.']);
                }

                $remaining = max(0, (float) $oldImport->grand_total - (float) $oldImport->paid_amount);
                if ($allocation['amount'] > $remaining + 0.01) {
                    throw ValidationException::withMessages(['old_debt_allocations' => "Số tiền trả cho {$oldImport->code} vượt quá công nợ còn lại."]);
                }

                $oldImport->increment('paid_amount', $allocation['amount']);
                return [...$allocation, 'code' => $oldImport->code];
            })->values();


            /*
             * ==========================================================
             * TẠO PHIẾU NHẬP
             * ==========================================================
             */

            $import = StockImport::create([
                'code' => $this->generateCode(),

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

                'tendered_amount' => $tenderedAmount,

                'change_amount' => $changeAmount,

                'old_debt_paid' => $oldDebtPaid,

                'old_debt_allocations' => $oldDebtAllocations->all() ?: null,

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

                // Sản phẩm cũ: không áp dụng VAT.
                $isUsed = filter_var($item['is_used'] ?? ($item['extra_info']['is_used'] ?? false), FILTER_VALIDATE_BOOLEAN);

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

                    'has_vat' => ! $isUsed && (bool) ($item['has_vat'] ?? false),

                    'is_used' => $isUsed,

                    'supplier_warranty_value' => $item['supplier_warranty_value'] ?? null,

                    'supplier_warranty_unit' => ! empty($item['supplier_warranty_value'])
                        ? ($item['supplier_warranty_unit'] ?? 'months')
                        : null,

                    'extra_info' => $item['extra_info'] ?? null,
                ]);


                // Hàng đang có tồn nhưng chưa có lô nào (tạo sản phẩm, import Excel...):
                // tạo lô đầu kỳ trước khi thêm lô mới, nếu không tồn cũ sẽ bị mất khi bán lần đầu.
                if (! $product->manage_stock_by_serial) {
                    $this->ensureOpeningBatch($product, $variantId, $data['import_date'] ?? null);
                }

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


                if ($variantId !== null && ! $product->manage_stock_by_serial) {
                    $this->syncVariantProductStock($productId);
                }

                /*
                 * ------------------------------------------------------
                 * LƯU IMEI
                 * ------------------------------------------------------
                 */

                foreach ($imeis as $imeiData) {
                    $imei = ProductImei::query()
                        ->withTrashed()
                        ->where('imei', $imeiData['imei'])
                        ->first();

                    $imeiPayload = [
                        'product_id' => $productId,
                        'variant_id' => $imeiData['variant_id'] ?? $variantId,
                        'imei' => $imeiData['imei'],
                        'cost_price' => $imeiData['cost_price'] ?? $costPrice,
                        'sell_price' => $imeiData['sell_price'] ?? $sellPrice ?? 0,
                        'extra_info' => $imeiData['extra_info'] ?? null,
                        'is_used' => $isUsed,
                        'warranty_expired_at' => $imeiData['warranty_expired_at'] ?? null,
                        'status' => ProductImei::STATUS_IN_STOCK,
                        'sold_at' => null,
                        'imported_at' => now(),
                    ];

                    if ($imei) {
                        // Khi sửa phiếu, bản cũ được hủy trước và IMEI được
                        // soft-delete. Khôi phục chính bản ghi này để không
                        // đụng unique index imei khi nhập lại cùng mã.
                        if ($imei->trashed()) {
                            $imei->restore();
                        }
                        $imei->update($imeiPayload);
                    } else {
                        $imei = ProductImei::create($imeiPayload);
                    }

                    // Mua lại máy từ khách: mọi luồng bảo hành cũ của thiết bị hết hiệu lực.
                    if (($data['party_type'] ?? 'supplier') === 'customer') {
                        $this->voidWarrantiesOnBuyback($imei, $import);
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
                            'stock_import_item_id' => $importItem->id,
                            'product_id' => $productId,
                            'variant_id' => $imeiPayload['variant_id'],
                            'has_vat' => ! $isUsed && (bool) ($item['has_vat'] ?? false),
                            'is_used' => $isUsed,
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

    /**
     * Cửa hàng mua lại thiết bị từ khách: vô hiệu hóa bảo hành mua bán (trên IMEI)
     * và bảo hành sửa chữa của các phiếu sửa từng có cho thiết bị này.
     */
    private function voidWarrantiesOnBuyback(ProductImei $imei, StockImport $import): void
    {
        $reason = 'Cửa hàng đã mua lại thiết bị (phiếu nhập ' . $import->code . ')';
        $now = now();

        if (! $imei->customer_warranty_voided_at) {
            $imei->update([
                'customer_warranty_voided_at' => $now,
                'customer_warranty_void_reason' => $reason,
                'customer_warranty_voided_by' => auth()->id(),
            ]);
        }

        $code = mb_strtolower(trim((string) $imei->imei));
        if ($code === '') {
            return;
        }

        Repair::query()
            ->whereNull('repair_warranty_voided_at')
            ->whereNotNull('repair_warranty_expires_at')
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(TRIM(imei)) = ?', [$code])
                ->orWhereRaw('LOWER(TRIM(serial)) = ?', [$code]))
            ->update([
                'repair_warranty_voided_at' => $now,
                'repair_warranty_void_reason' => $reason,
                'repair_warranty_voided_by' => auth()->id(),
            ]);
    }

    private function generateCode(): string
    {
        // Giữ định dạng IMP-YmdHis; nếu giây này đã có phiếu thì dời sang giây kế tiếp.
        $moment = now();
        while (StockImport::query()->where('code', 'IMP-' . $moment->format('YmdHis'))->exists()) {
            $moment = $moment->copy()->addSecond();
        }

        return 'IMP-' . $moment->format('YmdHis');
    }

    /** Tạo lô đầu kỳ cho lượng tồn có sẵn mà chưa thuộc lô nào. */
    private function ensureOpeningBatch(Product $product, ?int $variantId, ?string $importDate): void
    {
        $hasBatch = ProductStockBatch::query()
            ->where('product_id', $product->id)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->exists();
        if ($hasBatch) {
            return;
        }

        $holder = $variantId
            ? ProductVariant::query()->lockForUpdate()->find($variantId)
            : Product::query()->lockForUpdate()->find($product->id);
        $legacyStock = (int) ($holder?->stock ?? 0);
        if ($legacyStock <= 0) {
            return;
        }

        // Lô đầu kỳ phải xếp trước lô vừa nhập theo FIFO.
        $openingDate = collect([$product->created_at, $importDate ? Carbon::parse($importDate) : null])
            ->filter()
            ->min();

        ProductStockBatch::create([
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'initial_quantity' => $legacyStock,
            'remaining_quantity' => $legacyStock,
            'cost_price' => round((float) ($holder->cost_price ?? 0), 2),
            'sell_price' => (float) ($holder->sell_price ?? 0),
            'imported_at' => ($openingDate ?? now())->toDateString(),
            'is_opening_balance' => true,
        ]);
    }

    /** Sản phẩm có biến thể: tồn tổng của sản phẩm luôn bằng tổng tồn các biến thể (cùng quy ước với luồng bán hàng). */
    private function syncVariantProductStock(int $productId): void
    {
        $product = Product::query()->find($productId);
        if (! $product || $product->manage_stock_by_serial) {
            return;
        }
        if (! ProductVariant::query()->where('product_id', $productId)->exists()) {
            return;
        }

        $product->update(['stock' => (int) ProductVariant::query()->where('product_id', $productId)->sum('stock')]);
    }

    /** Trừ lại tồn đã cộng khi hủy/sửa phiếu; chặn nếu tồn hiện tại không đủ để hoàn tác. */
    private function reverseImportedStock($items): void
    {
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id')->unique())
            ->get(['id', 'name', 'manage_stock_by_serial'])
            ->keyBy('id');

        if (! filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN)) {
            $required = [];
            foreach ($items as $item) {
                if ($products->get($item->product_id)?->manage_stock_by_serial) {
                    continue; // Hàng IMEI: tồn lấy theo số IMEI, được kiểm tra ở bước IMEI.
                }
                $key = $item->variant_id ? 'v' . $item->variant_id : 'p' . $item->product_id;
                $required[$key] ??= ['product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'quantity' => 0];
                $required[$key]['quantity'] += (int) $item->base_quantity;
            }

            foreach ($required as $row) {
                $holder = $row['variant_id']
                    ? ProductVariant::query()->lockForUpdate()->find($row['variant_id'])
                    : Product::query()->lockForUpdate()->find($row['product_id']);
                $current = (int) ($holder?->stock ?? 0);

                if ($current < $row['quantity']) {
                    $name = $products->get($row['product_id'])?->name ?? 'sản phẩm';
                    throw ValidationException::withMessages([
                        'import' => "Không thể hoàn tác phiếu nhập: tồn kho hiện tại của {$name} ({$current}) thấp hơn số lượng đã nhập ({$row['quantity']}). Hàng có thể đã bán hoặc đã được điều chỉnh tồn.",
                    ]);
                }
            }
        }

        foreach ($items as $item) {
            if ($item->variant_id) {
                ProductVariant::query()->whereKey($item->variant_id)->decrement('stock', $item->base_quantity);
            } else {
                Product::query()->whereKey($item->product_id)->decrement('stock', $item->base_quantity);
            }
        }

        $items->pluck('product_id')->unique()->each(fn ($productId) => $this->syncVariantProductStock((int) $productId));
    }

    /** Không cho hủy/sửa phiếu đã có tiền trả bổ sung sau khi lập, vì sẽ làm mất lịch sử chi tiền. */
    private function assertNoLaterSupplierActivity(StockImport $import, string $action): void
    {
        if (! $import->supplier_id) {
            return;
        }

        $hasLaterPayment = SupplierDebt::query()
            ->where('source_type', StockImport::class)
            ->where('source_id', $import->id)
            ->where('type', 'decrease')
            ->where(fn ($query) => $query
                ->whereNull('note')
                ->orWhere(fn ($note) => $note
                    ->where('note', 'not like', 'Thanh toán khi tạo phiếu nhập%')
                    ->where('note', 'not like', 'Thanh toán nợ cũ khi tạo phiếu nhập%')))
            ->exists();

        $paidByLaterImport = StockImport::query()
            ->where('supplier_id', $import->supplier_id)
            ->where('status', 'completed')
            ->where('id', '!=', $import->id)
            ->whereNotNull('old_debt_allocations')
            ->get(['id', 'old_debt_allocations'])
            ->contains(fn (StockImport $other) => collect($other->old_debt_allocations ?? [])
                ->contains(fn ($allocation) => (int) ($allocation['stock_import_id'] ?? 0) === (int) $import->id));

        if ($hasLaterPayment || $paidByLaterImport) {
            throw ValidationException::withMessages([
                'import' => "Không thể {$action} phiếu nhập vì đã có khoản thanh toán công nợ phát sinh sau khi lập phiếu. Hủy hoặc sửa sẽ làm mất lịch sử chi tiền cho nhà cung cấp.",
            ]);
        }
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
                    // null = không gửi giá riêng → dùng giá của dòng nhập.
                    'cost_price' => is_array($imeiData) && isset($imeiData['cost_price']) && $imeiData['cost_price'] !== ''
                        ? (float) $imeiData['cost_price']
                        : null,
                    'sell_price' => is_array($imeiData) && isset($imeiData['sell_price']) && $imeiData['sell_price'] !== ''
                        ? (float) $imeiData['sell_price']
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
            'is_used' => filter_var($extraInfo['is_used'] ?? false, FILTER_VALIDATE_BOOLEAN) ? '1' : null,
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
