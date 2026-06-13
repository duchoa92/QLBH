<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerDebt;

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
                    return ((float) $item['price'] * (int) $item['quantity']);
                });

            $discount = 0;
            $grandTotal = $subtotal - $discount;

            // Nếu không có khách hàng và tiền thanh toán chưa đủ thì báo lỗi
            if (!$customerId && $paidAmount < $grandTotal) {
                throw new \Exception('Khách lẻ phải thanh toán đủ tiền');
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

            foreach ($items as $item) {
                /*
                |--------------------------------------------------------------------------
                | Lấy sản phẩm
                |--------------------------------------------------------------------------
                */
                $product = Product::query()->findOrFail($item['id']);

                /*
                |--------------------------------------------------------------------------
                | Kiểm tra IMEI
                |--------------------------------------------------------------------------
                */
                $imei = null;

                if ($product->product_type === 'imei') {
                    if (empty($item['imei_id'])) {
                        throw new \Exception("Sản phẩm {$product->name} phải quét IMEI");
                    }

                    $imei = ProductImei::query()
                        ->lockForUpdate()
                        ->findOrFail($item['imei_id']);

                    if ($imei->status !== ProductImei::STATUS_AVAILABLE) {
                        throw new \Exception("IMEI {$imei->imei} không khả dụng");
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Kiểm tra tồn kho sản phẩm thường
                |--------------------------------------------------------------------------
                */
                if ($product->product_type !== 'imei') {
                    if ($product->stock < (int) $item['quantity']) {
                        throw new \Exception("Sản phẩm {$product->name} không đủ tồn kho");
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Lưu chi tiết hóa đơn
                |--------------------------------------------------------------------------
                */
                $sale->items()->create([
                    'product_id' => $item['id'],
                    'product_imei_id' => $item['imei_id'] ?? null,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['price'],
                    'subtotal' => ((float) $item['price'] * (int) $item['quantity']),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Trừ tồn kho sản phẩm thường
                |--------------------------------------------------------------------------
                */
                if ($product->product_type !== 'imei') {
                    $product->decrement('stock', (int) $item['quantity']);
                }

                /*
                |--------------------------------------------------------------------------
                | Cộng số lượng đã bán
                |--------------------------------------------------------------------------
                */
                $product->increment('sold_count', (int) $item['quantity']);

                /*
                |--------------------------------------------------------------------------
                | Đánh dấu IMEI đã bán
                |--------------------------------------------------------------------------
                */
                if ($imei) {
                    $imei->update([
                        'status' => ProductImei::STATUS_SOLD,
                        'sold_at' => now(),
                    ]);
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
}