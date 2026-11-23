<?php

namespace App\Services\Stock;

use App\Models\OrderItemCancellation;
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

    /**
     * Hủy MỘT PHẦN phiếu nhập: chọn dòng nhập và số lượng cần hủy (lý do có thể để trống).
     * Hủy hết số lượng của dòng thì dòng bị xóa; hủy bớt (chỉ hàng không IMEI) thì dòng giảm số lượng.
     * Trừ lại tồn + lô + IMEI tương ứng, tính lại tổng tiền phiếu và công nợ nhà cung cấp.
     *
     * @param  array<int,int>  $selection  [stock_import_item_id => số lượng cần hủy]
     * @return array{import: StockImport, refund: float} refund = số tiền đã trả dư so với tổng mới (NCC/khách hoàn lại cửa hàng)
     */
    public function cancelItems(StockImport $stockImport, array $selection, ?string $reason = null): array
    {
        $selection = collect($selection)->mapWithKeys(fn ($qty, $id) => [(int) $id => (int) $qty])->filter(fn ($qty, $id) => $id > 0 && $qty > 0);
        if ($selection->isEmpty()) {
            throw ValidationException::withMessages(['import' => 'Chưa chọn sản phẩm cần hủy.']);
        }

        return DB::transaction(function () use ($stockImport, $selection, $reason): array {
            $import = StockImport::query()->lockForUpdate()->findOrFail($stockImport->id);
            if ($import->status !== 'completed') {
                throw ValidationException::withMessages(['import' => 'Chỉ hủy được sản phẩm của phiếu nhập đang hoàn thành.']);
            }

            $allItems = StockImportItem::query()
                ->where('stock_import_id', $import->id)
                ->with(['product:id,name,manage_stock_by_serial', 'variant'])
                ->lockForUpdate()
                ->get();
            $selected = $allItems->whereIn('id', $selection->keys()->all())->values();
            if ($selected->count() !== $selection->count()) {
                throw ValidationException::withMessages(['import' => 'Có sản phẩm không còn trong phiếu nhập (có thể đã được hủy). Hãy tải lại và thử lại.']);
            }

            $plan = $selected->map(function (StockImportItem $item) use ($selection) {
                $qty = (int) $selection[$item->id];
                if ($qty > (int) $item->quantity) {
                    throw ValidationException::withMessages(['import' => 'Số lượng hủy của "' . ($item->product?->name ?? 'sản phẩm') . '" vượt quá số lượng đã nhập.']);
                }

                return ['item' => $item, 'qty' => $qty, 'full' => $qty >= (int) $item->quantity];
            });
            if ($plan->every(fn ($row) => $row['full']) && $plan->count() >= $allItems->count()) {
                throw ValidationException::withMessages(['import' => 'Bạn đã chọn hủy toàn bộ sản phẩm. Hãy dùng chức năng hủy cả phiếu.']);
            }

            $this->assertNoLaterSupplierActivity($import, 'hủy sản phẩm của');

            $batchesByItem = ProductStockBatch::query()
                ->whereIn('stock_import_item_id', $selected->pluck('id'))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('stock_import_item_id');

            $histories = ProductImeiHistory::query()
                ->where('reference_type', StockImport::class)
                ->where('reference_id', $import->id)
                ->where('type', 'import')
                ->with('productImei')
                ->lockForUpdate()
                ->get();

            $reverseItems = collect();
            $applyBatches = [];   // callbacks sau khi kiểm tra xong
            $removeHistories = collect();
            $groups = [];         // product_id => dữ liệu snapshot

            foreach ($plan as $row) {
                /** @var StockImportItem $item */
                $item = $row['item'];
                $qty = $row['qty'];
                $factor = max(1, (int) ($item->conversion_factor ?: 1));
                $baseCancel = $qty * $factor;
                $batches = $batchesByItem->get($item->id, collect());
                $itemHistories = $histories->filter(fn (ProductImeiHistory $history) => (int) ($history->meta['stock_import_item_id'] ?? 0) === (int) $item->id)->values();
                $isSerial = $itemHistories->isNotEmpty() || $item->product?->manage_stock_by_serial;

                if ($row['full']) {
                    if ($batches->contains(fn (ProductStockBatch $batch) => (int) $batch->remaining_quantity !== (int) $batch->initial_quantity)) {
                        throw ValidationException::withMessages(['import' => 'Không thể hủy "' . ($item->product?->name ?? 'sản phẩm') . '" vì một phần hàng của dòng nhập này đã được bán.']);
                    }
                    if ($itemHistories->contains(fn (ProductImeiHistory $history) => $history->productImei?->status !== ProductImei::STATUS_IN_STOCK)) {
                        throw ValidationException::withMessages(['import' => 'Không thể hủy "' . ($item->product?->name ?? 'sản phẩm') . '" vì có IMEI đã bán hoặc không còn trong kho.']);
                    }
                    $reverseItems->push($item);
                    $removeHistories = $removeHistories->merge($itemHistories);
                    $applyBatches[] = function () use ($item, $batches) {
                        ProductStockBatch::query()->whereIn('id', $batches->pluck('id'))->delete();
                        $item->delete();
                    };
                } else {
                    if ($isSerial) {
                        throw ValidationException::withMessages(['import' => '"' . ($item->product?->name ?? 'Sản phẩm') . '" quản lý theo IMEI nên chỉ hủy được cả dòng, không hủy bớt số lượng.']);
                    }
                    if ((int) $batches->sum('remaining_quantity') < $baseCancel) {
                        throw ValidationException::withMessages(['import' => 'Không thể hủy bớt "' . ($item->product?->name ?? 'sản phẩm') . '" vì số lượng còn trong kho ít hơn số lượng cần hủy (hàng đã bán).']);
                    }
                    $reverseItems->push((clone $item)->setAttribute('base_quantity', $baseCancel));
                    $applyBatches[] = function () use ($item, $batches, $qty, $baseCancel) {
                        $need = $baseCancel;
                        foreach ($batches as $batch) {
                            if ($need <= 0) {
                                break;
                            }
                            $take = min($need, (int) $batch->remaining_quantity);
                            if ($take <= 0) {
                                continue;
                            }
                            $newInitial = (int) $batch->initial_quantity - $take;
                            $newRemaining = (int) $batch->remaining_quantity - $take;
                            if ($newInitial <= 0) {
                                $batch->delete();
                            } else {
                                $batch->update(['initial_quantity' => $newInitial, 'remaining_quantity' => $newRemaining]);
                            }
                            $need -= $take;
                        }
                        $item->update([
                            'quantity' => (int) $item->quantity - $qty,
                            'base_quantity' => max(0, (int) $item->base_quantity - $baseCancel),
                        ]);
                    };
                }

                $groups[$item->product_id] ??= ['item' => $item, 'quantity' => 0, 'amount' => 0.0, 'variants' => collect(), 'imeis' => collect()];
                $groups[$item->product_id]['quantity'] += $qty;
                $groups[$item->product_id]['amount'] += round($qty * (float) $item->cost_price, 2);
                $groups[$item->product_id]['variants']->push(OrderItemCancellation::variantLabel($item->variant));
                if ($row['full']) {
                    $groups[$item->product_id]['imeis'] = $groups[$item->product_id]['imeis']->merge($itemHistories->pluck('imei'));
                }
            }

            $lineAmount = round((float) collect($groups)->sum('amount'), 2);

            // Tồn kho / lô / IMEI.
            $this->reverseImportedStock($reverseItems);
            $removeHistories->each(fn (ProductImeiHistory $history) => $history->productImei?->delete());
            ProductImeiHistory::query()->whereKey($removeHistories->pluck('id'))->delete();
            foreach ($applyBatches as $apply) {
                $apply();
            }
            foreach ($groups as $group) {
                $first = $group['item'];
                $variants = $group['variants']->filter()->unique()->values();
                OrderItemCancellation::query()->create([
                    'order_type' => StockImport::class,
                    'order_id' => $import->id,
                    'product_id' => $first->product_id,
                    'product_name' => $first->product?->name ?? 'Sản phẩm đã xóa',
                    'variant_label' => $variants->isNotEmpty() ? $variants->implode(', ') : null,
                    'imeis' => $group['imeis']->filter()->values()->all() ?: null,
                    'quantity' => $group['quantity'],
                    'unit_name' => $first->unit_name,
                    'unit_price' => $group['quantity'] > 0 ? round($group['amount'] / $group['quantity'], 2) : 0,
                    'amount' => $group['amount'],
                    'reason' => trim((string) $reason) ?: null,
                    'user_id' => auth()->id(),
                    'cancelled_at' => now(),
                ]);
            }

            // Tính lại tiền phiếu.
            $oldTotalAmount = (float) $import->total_amount;
            $newTotalAmount = max(0, round($oldTotalAmount - $lineAmount, 2));
            $newDiscount = $oldTotalAmount > 0
                ? round(min($newTotalAmount, (float) $import->discount * $newTotalAmount / $oldTotalAmount), 2)
                : 0.0;
            $extraFee = (float) $import->extra_fee;
            $oldGrand = (float) $import->grand_total;
            $newGrand = max(0, round($newTotalAmount - $newDiscount + $extraFee, 2));
            $oldPaid = (float) $import->paid_amount;
            $oldDebtPaid = (float) $import->old_debt_paid;

            $newPaid = $import->supplier_id
                ? min($oldPaid, $newGrand)
                : ($oldPaid > 0 ? $newGrand : 0.0); // mua lại từ khách lẻ: luôn chi đủ theo tổng phiếu
            $refund = max(0, round($oldPaid - $newPaid, 2));

            if ($import->supplier_id) {
                $supplier = Supplier::query()->lockForUpdate()->find($import->supplier_id);
                $debtDelta = round(($newGrand - $newPaid) - ($oldGrand - $oldPaid), 2);
                if ($supplier && abs($debtDelta) > 0.001) {
                    $supplier->increment('debt_balance', $debtDelta);
                }

                $increase = SupplierDebt::query()
                    ->where('source_type', StockImport::class)
                    ->where('source_id', $import->id)
                    ->where('type', 'increase')
                    ->first();
                if ($increase) {
                    $newGrand > 0 ? $increase->update(['amount' => $newGrand]) : $increase->delete();
                }

                $payment = SupplierDebt::query()
                    ->where('source_type', StockImport::class)
                    ->where('source_id', $import->id)
                    ->where('type', 'decrease')
                    ->where('note', 'like', 'Thanh toán khi tạo phiếu nhập%')
                    ->first();
                if ($payment) {
                    $newPaid > 0 ? $payment->update(['amount' => $newPaid]) : $payment->delete();
                }
            }

            $import->update([
                'total_amount' => $newTotalAmount,
                'discount' => $newDiscount,
                'grand_total' => $newGrand,
                'paid_amount' => $newPaid,
                'tendered_amount' => $import->supplier_id ? $newPaid + $oldDebtPaid : $newPaid,
                'change_amount' => 0,
            ]);

            foreach ($groups as $productId => $group) {
                if ($group['item']->product?->manage_stock_by_serial) {
                    app(ImeiStockSynchronizer::class)->sync((int) $productId);
                }
            }

            return ['import' => $import->fresh(), 'refund' => $refund];
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
                $imeis = $this->inheritItemCustomerWarranty(
                    $this->normalizeImeis($item['imeis'] ?? [], $data['import_date'] ?? null),
                    $item['extra_info'] ?? null
                );

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
                        'is_used' => $isUsed,
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

    /**
     * BH khách ghi ở dòng nhập áp cho mọi IMEI của dòng đó. IMEI nhập hàng loạt (dán/quét/Excel) chỉ có mã,
     * không mang BH riêng, nên nếu thiếu thì lấy BH của dòng nhập; IMEI có BH riêng thì giữ nguyên.
     *
     * @param  array<int, array<string, mixed>>  $imeis
     * @return array<int, array<string, mixed>>
     */
    private function inheritItemCustomerWarranty(array $imeis, mixed $itemExtraInfo): array
    {
        $value = is_array($itemExtraInfo) ? (float) ($itemExtraInfo['customer_warranty_duration_value'] ?? 0) : 0;
        if ($value <= 0) {
            return $imeis;
        }

        $unit = $itemExtraInfo['customer_warranty_duration_unit'] ?? 'months';

        return array_map(function (array $imei) use ($itemExtraInfo, $unit): array {
            $extra = is_array($imei['extra_info'] ?? null) ? $imei['extra_info'] : [];
            if ((float) ($extra['customer_warranty_duration_value'] ?? 0) > 0) {
                return $imei;
            }

            $extra['customer_warranty_duration_value'] = (string) $itemExtraInfo['customer_warranty_duration_value'];
            $extra['customer_warranty_duration_unit'] = (string) $unit;
            $imei['extra_info'] = $extra;

            return $imei;
        }, $imeis);
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

        if ($value <= 0 || ! in_array($unit, ['days', 'weeks', 'months', 'years'], true)) {
            return null;
        }

        $date = $importDate
            ? Carbon::parse($importDate)->startOfDay()
            : now()->startOfDay();

        return match ($unit) {
            'years' => $date->addYears($value),
            'months' => $date->addMonths($value),
            'weeks' => $date->addWeeks($value),
            default => $date->addDays($value),
        };
    }
}
