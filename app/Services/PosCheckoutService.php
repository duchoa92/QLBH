<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\SaleItemGift;

class PosCheckoutService
{
    public function checkout(
        array $items,
        ?int $customerId,
        float $paidAmount,
        ?string $paymentMethod, // SỬA LỖI TYPEERROR: Chuyển thành ?string để chấp nhận null
        bool $payOldDebt,
        int $userId,
    ): Sale {

        return DB::transaction(function () use (
            $items,
            $customerId,
            $paidAmount,
            $paymentMethod,
            $payOldDebt,
            $userId,
        ): Sale {

            /*
            |--------------------------------------------------------------------------
            | Tính toán tổng tiền hóa đơn
            |--------------------------------------------------------------------------
            */

            $subtotal = collect($items)
                ->sum(function (array $item): float {
                    return (float) $item['price'] * $this->itemQuantity($item);
                });

            //
            $discount = collect($items)
                ->sum(function ($item) {

                    $lineTotal =

                        (float) $item['price']
                        *
                        $this->itemQuantity($item);

                    if (
                        ($item['discount_type'] ?? null)
                        === 'percent'
                    ) {

                        return
                            $lineTotal
                            *
                            ((float) $item['discount_value'])
                            / 100;
                    }

                    if (
                        ($item['discount_type'] ?? null)
                        === 'amount'
                    ) {

                        return
                            (float) $item['discount_value'];
                    }

                    return 0;
                });

            $grandTotal = $subtotal - $discount;

            // Nếu không có khách hàng và tiền thanh toán chưa đủ thì báo lỗi
            if (!$customerId && $paidAmount < $grandTotal) {
                throw new \Exception('Khách lạ phải thanh toán đủ tiền');
            }
            
            /*
            |--------------------------------------------------------------------------
            | Tạo hóa đơn bán hàng
            |--------------------------------------------------------------------------
            */

            $sale = Sale::query()->create([
                'code' => $this->generateCode(),
                'customer_id' => $customerId,
                'user_id' => $userId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'change_amount' => 0, // Sẽ cập nhật lại ở cuối sau khi tính toán chính xác
                'payment_method' => $paymentMethod ?? 'cash', // Phòng hờ nếu null thì lưu là 'cash'
            ]);

            /*
            |--------------------------------------------------------------------------
            | Xử lý từng sản phẩm trong hóa đơn
            |--------------------------------------------------------------------------
            */
            $totalDiscount = 0;

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
                    : $this->itemQuantity($item);

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
                            throw new \Exception("IMEI {$imei->imei} không thuộc phiên bản đã chọn");
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
                if (! $requiresImei) {

                    if ($variant) {

                        if ($variant->stock < $quantity) {
                            throw new \Exception("Phiên bản {$product->name} không đủ tồn kho");
                        }

                    } elseif ($product->stock < $quantity) {

                        throw new \Exception("Sản phẩm {$product->name} không đủ tồn kho");
                    }
                }



                $lineTotal =
                    (float) $item['price']
                    *
                    $quantity;

                $lineDiscount = 0;

                if (
                    ($item['discount_type'] ?? null)
                    === 'percent'
                ) {

                    $lineDiscount =

                        $lineTotal
                        *
                        ((float) $item['discount_value'])
                        / 100;
                }

                if (
                    ($item['discount_type'] ?? null)
                    === 'amount'
                ) {

                    $lineDiscount =
                        (float) $item['discount_value'];
                }

                $totalDiscount +=
                    $lineDiscount;
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
                        $item['imei_id'] ?? null,

                    'unit_id' =>
                        $item['unit_id'] ?? $product->unit_id,

                    'unit_name' =>
                        $item['unit_name'] ?? ($product->unit?->short_name ?: $product->unit?->name),

                    'quantity' =>
                        $quantity,

                    'unit_price' =>
                        (float) $item['price'],

                    'discount_type' =>
                        $item['discount_type'] ?? null,

                    'discount_value' =>
                        (float) ($item['discount_value'] ?? 0),

                    'discount_amount' =>
                        $lineDiscount,

                    'subtotal' =>
                        $lineTotal - $lineDiscount,

                    'note' =>
                        $item['note'] ?? null,
                ]);


                if (!empty($item['gifts'])) {
                    foreach ($item['gifts'] as $gift) {
                        SaleItemGift::create([
                            'sale_item_id' => $saleItem->id,
                            'product_id' => $gift['id'],
                            'quantity' => (int) (
                                $gift['quantity'] ?? 1
                            ),
                        ]);
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
                    $imei->update([
                        'status' => ProductImei::STATUS_SOLD,
                        'sold_at' => now(),
                    ]);

                    /*
                    * Đồng bộ tồn Variant theo số IMEI thực tế còn trong kho.
                    */
                    if ($variant) {

                        $variantStock = ProductImei::query()
                            ->where('product_id', $product->id)
                            ->where('variant_id', $variant->id)
                            ->where(
                                'status',
                                ProductImei::STATUS_IN_STOCK
                            )
                            ->count();

                        $variant->update([
                            'stock' => $variantStock,
                        ]);
                    }

                    /*
                    * Đồng bộ tồn Product theo tổng IMEI còn trong kho.
                    */
                    $productStock = ProductImei::query()
                        ->where('product_id', $product->id)
                        ->where(
                            'status',
                            ProductImei::STATUS_IN_STOCK
                        )
                        ->count();

                    $product->update([
                        'stock' => $productStock,
                    ]);

                } elseif ($variant) {

                    /*
                    * Sản phẩm thường có variant.
                    * Giảm cả variant và tổng tồn của product.
                    */
                    $this->decrementStockSafely(
                        $variant,
                        $quantity
                    );

                    $this->decrementStockSafely(
                        $product,
                        $quantity
                    );

                } else {

                    /*
                    * Sản phẩm thường không có variant.
                    */
                    $this->decrementStockSafely(
                        $product,
                        $quantity
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
                        $qty = (int) ($gift['quantity'] ?? 1);
                        if ($giftProduct->stock < $qty) {
                            throw new \Exception(
                                'Quà tặng '
                                . $giftProduct->name
                                . ' không đủ tồn kho'
                            );
                        }
                        $giftProduct->decrement(
                            'stock',
                            $qty
                        );
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
        return 'INV-' . now()->format('YmdHis');
    }

    private function itemQuantity(array $item): int
    {
        return max(1, (int) ($item['quantity'] ?? 1));
    }

    /*
    |--------------------------------------------------------------------------
    | Trừ tồn kho an toàn (không cho về số âm)
    |--------------------------------------------------------------------------
    |
    | Dùng CASE WHEN ngay trong câu UPDATE để: (1) vẫn atomic như
    | decrement() bình thường (không cần lock thêm), (2) không bao giờ
    | cho kết quả âm - tránh lỗi khi cột "stock" của bảng products là
    | unsignedInteger (ví dụ sản phẩm IMEI được thêm IMEI trực tiếp từ
    | form sửa sản phẩm mà chưa từng "Nhập kho" nên stock đang là 0).
    |
    */
    private function decrementStockSafely(Product|ProductVariant $model, int $quantity): void
    {
        $model->newQuery()
            ->whereKey($model->getKey())
            ->update([
                'stock' => DB::raw(
                    'CASE WHEN stock > ' . $quantity . ' THEN stock - ' . $quantity . ' ELSE 0 END'
                ),
            ]);
    }
}
