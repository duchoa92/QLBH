<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\SaleItemGift;
use App\Models\StockImportItem;
use App\Services\Stock\ImeiStockSynchronizer;
use App\Services\Stock\StockBatchAllocator;
use App\Support\PosTotals;

class PosCheckoutService
{
    public function checkout(
        array $items,
        ?int $customerId,
        float $paidAmount,
        ?string $paymentMethod, // SỬA LỖI TYPEERROR: Chuyển thành ?string để chấp nhận null
        bool $payOldDebt,
        int $userId,
        ?string $invoiceCode = null,
        ?float $cashReceivedAmount = null,
        ?float $bankReceivedAmount = null,
        float $invoiceDiscount = 0,
        string $invoiceDiscountType = 'amount',
        float $extraFee = 0,
        float $vatRate = 0,
        ?int $workShiftId = null,
        ?string $bankAccount = null,
    ): Sale {

        return DB::transaction(function () use (
            $items,
            $customerId,
            $paidAmount,
            $paymentMethod,
            $payOldDebt,
            $userId,
            $invoiceCode,
            $cashReceivedAmount,
            $bankReceivedAmount,
            $invoiceDiscount,
            $invoiceDiscountType,
            $extraFee,
            $vatRate,
            $workShiftId,
            $bankAccount,
        ): Sale {

            /*
            |--------------------------------------------------------------------------
            | Tính toán tổng tiền hóa đơn
            |--------------------------------------------------------------------------
            */

            // Sản phẩm cũ không chịu VAT: không tin cờ apply_vat từ client với IMEI đã nhập là hàng cũ.
            $usedImeiIds = ProductImei::query()
                ->whereIn('id', collect($items)->pluck('imei_id')->filter()->unique())
                ->where('is_used', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($usedImeiIds !== []) {
                $items = array_map(function ($item) use ($usedImeiIds) {
                    if (! empty($item['imei_id']) && in_array((int) $item['imei_id'], $usedImeiIds, true)) {
                        $item['apply_vat'] = false;
                    }

                    return $item;
                }, $items);
            }

            [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'invoice_discount' => $invoiceDiscount,
                'tax' => $tax,
                'extra_fee' => $extraFee,
                'grand_total' => $grandTotal,
            ] = PosTotals::calculate($items, $invoiceDiscount, $invoiceDiscountType, $extraFee, $vatRate);
            $vatProductIds = StockImportItem::query()
                ->whereIn('product_id', collect($items)->pluck('id')->unique())
                ->where('has_vat', true)->where('is_used', false)
                ->distinct()
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Nếu không có khách hàng và tiền thanh toán chưa đủ thì báo lỗi
            if (!$customerId && $paidAmount < $grandTotal) {
                throw new \Exception('Khách lạ phải thanh toán đủ tiền');
            }
            
            /*
            |--------------------------------------------------------------------------
            | Tạo hóa đơn bán hàng
            |--------------------------------------------------------------------------
            */

            $cashReceivedAmount ??= $paymentMethod === 'cash' ? $paidAmount : 0;
            $bankReceivedAmount ??= $paymentMethod === 'bank' ? $paidAmount : 0;

            $sale = Sale::query()->create([
                'code' => $invoiceCode ?: $this->generateCode(),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'invoice_discount' => $invoiceDiscount,
                'tax' => $tax,
                'extra_fee' => $extraFee,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'cash_received_amount' => $cashReceivedAmount,
                'bank_received_amount' => $bankReceivedAmount,
                'change_amount' => 0, // Sẽ cập nhật lại ở cuối sau khi tính toán chính xác
                'payment_method' => $paymentMethod ?? 'cash', // Phòng hờ nếu null thì lưu là 'cash'
                'work_shift_id' => $workShiftId,
                'bank_account' => in_array($paymentMethod, ['bank', 'mixed'], true) ? $bankAccount : null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Xử lý từng sản phẩm trong hóa đơn
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {
                /*
                |--------------------------------------------------------------------------
                | Lấy sản phẩm
                |--------------------------------------------------------------------------
                */
                $product = Product::query()
                    ->with('unit:id,name,short_name')
                    ->lockForUpdate()
                    ->findOrFail($item['id']);
                $requiresImei =
                    $product->product_type === 'imei'
                    || (bool) $product->manage_stock_by_serial;

                $quantity = $requiresImei
                    ? 1
                    : PosTotals::lineQuantity($item);
                // POS luôn bán theo đơn vị tồn kho (chiếc, mét...). Quy cách
                // cuộn/thùng chỉ được áp dụng khi nhập hàng.
                $conversionFactor = 1;
                $baseQuantity = $quantity * $conversionFactor;

                /*
                |--------------------------------------------------------------------------
                | Lấy biến thể (nếu có)
                |--------------------------------------------------------------------------
                */
                $variant = null;

                if (!empty($item['variant_id'])) {

                    $variant = ProductVariant::query()
                        ->lockForUpdate()
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->findOrFail($item['variant_id']);
                }

                if (! $requiresImei && ! $variant && $product->variants()->where('is_active', true)->exists()) {
                    throw new \Exception("Sản phẩm {$product->name} phải chọn biến thể");
                }

                /*
                |--------------------------------------------------------------------------
                | Kiểm tra IMEI
                |--------------------------------------------------------------------------
                */
                $imei = null;

                if ($requiresImei) {
                    if (empty($item['imei_id'])) {
                        throw new \Exception("Sản phẩm {$product->name} phải quét IMEI");
                    }

                    $imei = ProductImei::query()
                        ->lockForUpdate()
                        ->with('variant')
                        ->findOrFail($item['imei_id']);

                    if ((int) $imei->product_id !== (int) $product->id) {
                        throw new \Exception("IMEI {$imei->imei} không thuộc sản phẩm {$product->name}");
                    }

                    if ($imei->status !== ProductImei::STATUS_IN_STOCK) {
                        throw new \Exception("IMEI {$imei->imei} không khả dụng");
                    }

                    if ($imei->variant_id) {
                        if ($variant && (int) $variant->id !== (int) $imei->variant_id) {
                            throw new \Exception("IMEI {$imei->imei} không thuộc biến thể đã chọn");
                        }

                        if (! $variant) {
                            $variant = ProductVariant::query()
                                ->lockForUpdate()
                                ->where('product_id', $product->id)
                                ->where('is_active', true)
                                ->findOrFail($imei->variant_id);
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Kiểm tra tồn kho sản phẩm thường (theo biến thể nếu có)
                |--------------------------------------------------------------------------
                */
                $isBatchBacked = ! $requiresImei && $this->hasStockBatches($product->id, $variant?->id);

                if (! $requiresImei) {
                    $allowNegativeStock = filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN);

                    if (! $allowNegativeStock && $isBatchBacked && $this->availableBatchQuantity($product->id, $variant?->id) < $baseQuantity) {
                        throw new \Exception("Sản phẩm {$product->name} không đủ tồn kho theo lô");
                    }

                    if (! $isBatchBacked && $variant) {

                        if (! $allowNegativeStock && $variant->stock < $baseQuantity) {
                            throw new \Exception("Biến thể của sản phẩm {$product->name} không đủ tồn kho");
                        }

                    } elseif (! $isBatchBacked && ! $allowNegativeStock && $product->stock < $baseQuantity) {

                        throw new \Exception("Sản phẩm {$product->name} không đủ tồn kho");
                    }
                }



                $lineTotal = (float) $item['price'] * $quantity;
                $lineDiscount = PosTotals::lineDiscount($item);

                /*
                |--------------------------------------------------------------------------
                | Lưu chi tiết hóa đơn
                |--------------------------------------------------------------------------
                */
                $saleItem = $sale->items()->create([
                    'product_id' => $item['id'],

                    'variant_id' =>
                        $variant?->id,

                    'product_imei_id' =>
                        $imei?->id,

                    'unit_id' => $product->unit_id,

                    'unit_name' => $product->unit?->short_name ?: $product->unit?->name,

                    'quantity' =>
                        $quantity,

                    'base_quantity' => $baseQuantity,

                    'conversion_factor' => $conversionFactor,

                    'unit_price' =>
                        (float) $item['price'],

                    'unit_cost' => (float) ($imei?->cost_price ?? $variant?->cost_price ?? $product->cost_price ?? 0),

                    'discount_type' =>
                        $item['discount_type'] ?? null,

                    'discount_value' =>
                        (float) ($item['discount_value'] ?? 0),

                    'warranty_days' =>
                        max(0, min(3650, (int) ($item['warranty_days'] ?? 0))),

                    'warranty_value' =>
                        isset($item['warranty_value'])
                            ? max(0, min(3650, (int) $item['warranty_value']))
                            : null,

                    'warranty_unit' =>
                        in_array(($item['warranty_unit'] ?? null), ['day', 'month'], true)
                            ? $item['warranty_unit']
                            : null,

                    'warranty_note' =>
                        filled($item['warranty_note'] ?? null)
                            ? trim($item['warranty_note'])
                            : null,

                    'discount_amount' =>
                        $lineDiscount,

                    'has_vat' => ! ($imei?->is_used) && in_array((int) $product->id, $vatProductIds, true),
                    'vat_applied' => ! ($imei?->is_used) && filter_var($item['apply_vat'] ?? false, FILTER_VALIDATE_BOOLEAN),

                    'subtotal' =>
                        $lineTotal - $lineDiscount,

                    'note' =>
                        $item['note'] ?? null,
                ]);

                if (! $imei) {
                    $allocation = app(StockBatchAllocator::class)->allocate(
                        $saleItem,
                        (int) $product->id,
                        $variant?->id,
                        $baseQuantity,
                    );
                    if ($allocation['allocated_quantity'] > 0) {
                        // unit_cost của sale item luôn cùng đơn vị với quantity
                        // hiển thị trên hóa đơn, kể cả khi sản phẩm có quy đổi.
                        $saleItem->update([
                            'unit_cost' => round($allocation['total_cost'] / $quantity, 2),
                        ]);
                    }
                }


                if (!empty($item['gifts'])) {
                    foreach ($item['gifts'] as $gift) {
                        // Khởi tạo lại mỗi vòng: tránh lỗi biến chưa khai báo và lấy nhầm giá vốn của quà IMEI ở vòng trước.
                        $giftImei = null;
                        $giftProduct = Product::query()->findOrFail($gift['id']);
                        $giftQuantity = max(1, (int) ($gift['quantity'] ?? 1));
                        $giftFactor = 1;
                        $giftRequiresImei =
                            $giftProduct->product_type === 'imei'
                            || (bool) $giftProduct->manage_stock_by_serial;
                        $giftVariant = null;

                        if ($giftRequiresImei && empty($gift['imei_id'])) {
                            throw new \Exception(
                                'Vui lòng chọn IMEI cho quà tặng ' . $giftProduct->name
                            );
                        }

                        if ($giftRequiresImei) {
                            $giftImei = ProductImei::query()
                                ->where('product_id', $giftProduct->id)
                                ->findOrFail($gift['imei_id']);

                            if ($giftImei->variant_id) {
                                $giftVariant = ProductVariant::query()
                                    ->where('product_id', $giftProduct->id)
                                    ->where('is_active', true)
                                    ->findOrFail($giftImei->variant_id);
                            }
                        } elseif ($giftProduct->variants()->where('is_active', true)->exists()) {
                            if (empty($gift['variant_id'])) {
                                throw new \Exception(
                                    'Vui lòng chọn biến thể cho quà tặng ' . $giftProduct->name
                                );
                            }

                            $giftVariant = ProductVariant::query()
                                ->where('product_id', $giftProduct->id)
                                ->where('is_active', true)
                                ->findOrFail($gift['variant_id']);
                        }

                        if ($giftRequiresImei) {
                            $giftQuantity = 1;
                        }

                        $giftLine = SaleItemGift::create([
                            'sale_item_id' => $saleItem->id,
                            'product_id' => $gift['id'],
                            'variant_id' => $giftVariant?->id,
                            'product_imei_id' => $gift['imei_id'] ?? null,
                            'quantity' => $giftQuantity,
                            'unit_cost' => (float) ($giftImei?->cost_price ?? $giftVariant?->cost_price ?? $giftProduct->cost_price ?? 0),
                            'base_quantity' => $giftQuantity * $giftFactor,
                            'conversion_factor' => $giftFactor,
                        ]);

                        if (! $giftRequiresImei) {
                            $giftAllocation = app(StockBatchAllocator::class)->allocate(
                                $giftLine,
                                (int) $giftProduct->id,
                                $giftVariant?->id,
                                $giftQuantity * $giftFactor,
                            );
                            if ($giftAllocation['allocated_quantity'] > 0) {
                                $giftLine->update([
                                    'unit_cost' => round($giftAllocation['total_cost'] / $giftQuantity, 2),
                                ]);
                            }
                        }
                    }
                }
                /*
                |----------------------------------------------------------------------
                | Trừ / đồng bộ tồn kho
                |----------------------------------------------------------------------
                |
                | 1. IMEI:
                |    - Đánh dấu IMEI đã bán.
                |    - Variant.stock = số IMEI còn available của variant.
                |    - Product.stock = tổng số IMEI còn available của product.
                |
                | 2. Không có IMEI:
                |    - Nếu có variant: giảm variant.stock và product.stock.
                |    - Nếu không có variant: giảm product.stock.
                |
                */

                if ($imei) {

                    // IMEI -> đã bán
                    $soldAt = now();
                    $warrantyDays = max(0, min(3650, (int) ($item['warranty_days'] ?? $product->warranty_days)));
                    $imei->update([
                        'status' => ProductImei::STATUS_SOLD,
                        'sold_at' => $soldAt,
                        'customer_warranty_days' => $warrantyDays,
                        'customer_warranty_expires_at' => $warrantyDays > 0 ? $soldAt->copy()->addDays($warrantyDays) : null,
                        'customer_warranty_voided_at' => null,
                        'customer_warranty_void_reason' => null,
                        'customer_warranty_voided_by' => null,
                    ]);

                    app(ImeiStockSynchronizer::class)->sync($product);

                } elseif ($isBatchBacked) {

                    // StockBatchAllocator đã trừ đúng từng lô ở trên. Cột tồn cũng
                    // chỉ trừ đúng lượng đã bán (không ghi đè bằng tổng lô), cùng
                    // quy ước với sửa/hủy hóa đơn, nhập kho và sửa chữa. Ghi đè
                    // sẽ xóa mất tồn âm và làm "sống lại" hàng đã xuất ở luồng khác.
                    if ($variant) {
                        $this->decrementBatchBackedStock($variant, $baseQuantity);
                    }

                    $this->decrementBatchBackedStock($product, $baseQuantity);

                } elseif ($variant) {

                    /*
                    * Sản phẩm thường có variant.
                    * Giảm cả variant và tổng tồn của product.
                    */
                    $this->decrementStockSafely(
                        $variant,
                        $baseQuantity
                    );

                    $this->decrementStockSafely(
                        $product,
                        $baseQuantity
                    );

                } else {

                    /*
                    * Sản phẩm thường không có variant.
                    */
                    $this->decrementStockSafely(
                        $product,
                        $baseQuantity
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Cộng số lượng đã bán
                |--------------------------------------------------------------------------
                */
                $product->increment('sold_count', $quantity);

                /*
                |--------------------------------------------------------------------------
                | Trừ tồn kho quà tặng
                |--------------------------------------------------------------------------
                */

                if (!empty($item['gifts'])) {
                    foreach ($item['gifts'] as $gift) {
                        $giftProduct = Product::query()
                            ->lockForUpdate()
                            ->find($gift['id']);
                        if (!$giftProduct) {
                            continue;
                        }
                        $qty = max(1, (int) ($gift['quantity'] ?? 1));
                        $giftBaseQuantity = $qty;

                        $giftRequiresImei =
                            $giftProduct->product_type === 'imei'
                            || (bool) $giftProduct->manage_stock_by_serial;

                        if ($giftRequiresImei) {
                            $giftImei = ProductImei::query()
                                ->lockForUpdate()
                                ->findOrFail($gift['imei_id'] ?? null);

                            if ((int) $giftImei->product_id !== (int) $giftProduct->id) {
                                throw new \Exception(
                                    'IMEI quà tặng không thuộc sản phẩm ' . $giftProduct->name
                                );
                            }

                            if ($giftImei->status !== ProductImei::STATUS_IN_STOCK) {
                                throw new \Exception(
                                    'IMEI quà tặng ' . $giftImei->imei . ' không còn khả dụng'
                                );
                            }

                            $soldAt = now();
                            $warrantyDays = max(0, min(3650, (int) $giftProduct->warranty_days));

                            $giftImei->update([
                                'status' => ProductImei::STATUS_SOLD,
                                'sold_at' => $soldAt,
                                'customer_warranty_days' => $warrantyDays,
                                'customer_warranty_expires_at' => $warrantyDays > 0
                                    ? $soldAt->copy()->addDays($warrantyDays)
                                    : null,
                                'customer_warranty_voided_at' => null,
                                'customer_warranty_void_reason' => null,
                                'customer_warranty_voided_by' => null,
                            ]);

                            app(ImeiStockSynchronizer::class)->sync($giftProduct);

                            $giftProduct->increment('sold_count');

                            continue;
                        }

                        $giftVariant = null;

                        if ($giftProduct->variants()->where('is_active', true)->exists()) {
                            if (empty($gift['variant_id'])) {
                                throw new \Exception(
                                    'Vui lòng chọn biến thể cho quà tặng ' . $giftProduct->name
                                );
                            }

                            $giftVariant = ProductVariant::query()
                                ->lockForUpdate()
                                ->where('product_id', $giftProduct->id)
                                ->where('is_active', true)
                                ->findOrFail($gift['variant_id']);
                        }

                        if (! filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN)
                            && ($giftVariant?->stock ?? $giftProduct->stock) < $giftBaseQuantity) {
                            throw new \Exception(
                                'Quà tặng '
                                . $giftProduct->name
                                . ' không đủ tồn kho'
                            );
                        }
                        if ($giftVariant) {
                            $this->decrementStockSafely($giftVariant, $giftBaseQuantity);
                        }
                        $this->decrementStockSafely($giftProduct, $giftBaseQuantity);
                        $giftProduct->increment(
                            'sold_count',
                            $qty
                        );
                    }
                }
                
            }

            /*
            |--------------------------------------------------------------------------
            | XỬ LÝ CÔNG NỢ & TIỀN THỪA (ĐÃ ĐƯỢC TỐI ƯU TOÀN DIỆN VÀ CHÍNH XÁC)
            |--------------------------------------------------------------------------
            */
            if ($customerId) {
                $customer = Customer::query()->lockForUpdate()->find($customerId);
                
                if ($customer) {
                    $debtPaid = 0;
                    $newDebt = 0;

                    if ($payOldDebt) {
                        // Trường hợp giao diện chọn: CÓ thanh toán nợ cũ kèm hóa đơn này
                        $moneyForInvoice = min($paidAmount, $grandTotal);
                        $newDebt = max(0.0, $grandTotal - $moneyForInvoice);

                        $moneyForOldDebt = max(0.0, $paidAmount - $grandTotal);
                        $debtPaid = min($customer->debt_balance, $moneyForOldDebt);
                    } else {
                        // Trường hợp giao diện chọn: KHÔNG thanh toán nợ cũ (Tiền đưa chỉ tính cho đơn này)
                        if ($paidAmount < $grandTotal) {
                            $newDebt = $grandTotal - $paidAmount;
                        }
                    }

                    // 1. Nếu phát sinh nợ mới (Tiền đưa thiếu so với giá trị hóa đơn)
                    if ($newDebt > 0) {
                        CustomerDebt::query()->create([
                            'customer_id' => $customerId,
                            'type' => 'increase',
                            'amount' => $newDebt,
                            'source_type' => Sale::class,
                            'source_id' => $sale->id,
                            'note' => 'Mua nợ - Tiền hàng còn thiếu của hóa đơn ' . $sale->code,
                        ]);

                        $customer->increment('debt_balance', $newDebt);
                    }

                    // 2. Nếu có khấu trừ trả bớt nợ cũ (Khi tiền đưa vượt quá tiền hóa đơn)
                    if ($debtPaid > 0) {
                        CustomerDebt::query()->create([
                            'customer_id' => $customer->id,
                            'type' => 'decrease',
                            'amount' => $debtPaid,
                            'payment_method' => $paymentMethod ?? 'cash',
                            'source_type' => Sale::class,
                            'source_id' => $sale->id,
                            'note' => 'Trích tiền thừa hóa đơn ' . $sale->code . ' để thanh toán nợ cũ',
                        ]);

                        $customer->decrement('debt_balance', $debtPaid);
                    }

                    // 3. Tính toán tiền trả lại cho khách chính xác
                    $usedMoney = $grandTotal + $debtPaid;
                    $changeMoney = max(0.0, $paidAmount - $usedMoney);
                } else {
                    // Dự phòng nếu không tìm thấy customer trong database
                    $changeMoney = max(0.0, $paidAmount - $grandTotal);
                }
            } else {
                // Khách lẻ
                $changeMoney = max(0.0, $paidAmount - $grandTotal);
            }

            // Cập nhật lại số tiền thừa thực tế vào hóa đơn
            $sale->update([
                'change_amount' => $changeMoney,
            ]);

            app(CustomerPointService::class)->syncSale($sale);

            return $sale;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Generate invoice code
    |--------------------------------------------------------------------------
    */
    private function generateCode(): string
    {
        // Giữ đúng định dạng INV-14 chữ số (POS và webhook ngân hàng đều kiểm tra regex này),
        // nhưng nếu giây này đã có hóa đơn thì dời sang giây kế tiếp để không trùng mã.
        $moment = now();
        while (Sale::query()->where('code', 'INV-' . $moment->format('YmdHis'))->exists()) {
            $moment = $moment->copy()->addSecond();
        }

        return 'INV-' . $moment->format('YmdHis');
    }

    /*
    |--------------------------------------------------------------------------
    | Trừ tồn kho an toàn (không cho về số âm)
    |--------------------------------------------------------------------------
    |
    | Khi cho phép tồn âm, giảm trực tiếp trong transaction đang khóa bản
    | ghi. Nếu không cho phép tồn âm, dùng CASE để tránh race-condition và
    | không đưa số tồn xuống dưới 0.
    |
    */
    private function decrementStockSafely(Product|ProductVariant $model, int $quantity): void
    {
        if (filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN)) {
            $model->decrement('stock', $quantity);
            return;
        }

        $updated = $model->newQuery()
            ->whereKey($model->getKey())
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity);

        if ($updated === 0) {
            throw new \Exception('Sản phẩm không đủ tồn kho');
        }
    }

    private function hasStockBatches(int $productId, ?int $variantId): bool
    {
        return ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->exists();
    }

    private function availableBatchQuantity(int $productId, ?int $variantId): int
    {
        return (int) ProductStockBatch::query()
            ->where('product_id', $productId)
            ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
            ->sum('remaining_quantity');
    }

    /**
     * Trừ cột tồn cho hàng đã được trừ theo lô. Lô đã được kiểm tra đủ hàng nên không
     * chặn theo cột tồn; nếu không cho tồn âm thì chỉ chặn không cho cột tồn xuống dưới 0.
     */
    private function decrementBatchBackedStock(Product|ProductVariant $model, int $quantity): void
    {
        if (filter_var(setting('allow_negative_stock', false), FILTER_VALIDATE_BOOLEAN)) {
            $model->decrement('stock', $quantity);
            return;
        }

        $quantity = max(0, $quantity);
        $model->newQuery()
            ->whereKey($model->getKey())
            ->update(['stock' => DB::raw("CASE WHEN stock >= {$quantity} THEN stock - {$quantity} ELSE 0 END")]);
    }
}
