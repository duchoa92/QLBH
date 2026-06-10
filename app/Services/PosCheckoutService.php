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
        string $paymentMethod,
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
            | Totals
            |--------------------------------------------------------------------------
            */

            $subtotal = collect($items)
                ->sum(function (
                    array $item
                ): float {

                    return
                        (
                            (float) $item['price']
                            *
                            (int) $item['quantity']
                        );
                });

            $discount = 0;

            $grandTotal =
                $subtotal - $discount;

            // Tạm tính tiền thừa (nếu có)
            $changeMoney = 0;

            /*
            |--------------------------------------------------------------------------
            | Create Sale
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

                'change_amount' => $changeMoney,

                'payment_method' => $paymentMethod,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Sale Items
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {

                $sale->items()->create([

                    'product_id' =>
                        $item['id'],

                    'product_imei_id' =>
                        $item['imei_id'] ?? null,

                    // DB THẬT
                    'quantity' =>
                        (int) $item['quantity'],

                    // DB THẬT
                    'unit_price' =>
                        (float) $item['price'],

                    // DB THẬT
                    'subtotal' =>
                        (
                            (float) $item['price']
                            *
                            (int) $item['quantity']
                        ),
                ]);

                // 
                $product = Product::query()
                    ->findOrFail(
                        $item['id']
                    );

                /*
                |--------------------------------------------------
                | IMEI bắt buộc nếu sản phẩm có kiểu IMEI
                |--------------------------------------------------
                */
                if (
                    $product->product_type === 'imei'
                    &&
                    empty($item['imei_id'])
                ) {

                    throw new \Exception(
                        "Sản phẩm {$product->name} phải quét IMEI"
                    );
                }
                /*
                |--------------------------------------------------------------------------
                | Sản phẩm IMEI
                |--------------------------------------------------------------------------
                */

                if (!empty($item['imei_id'])) {

                    $imei = ProductImei::query()
                        ->findOrFail(
                            $item['imei_id']
                        );

                    if (
                        $imei->status !==
                        ProductImei::STATUS_AVAILABLE
                    ) {

                        throw new \Exception(
                            "IMEI {$imei->imei} không khả dụng"
                        );
                    }

                    
                }

                /*
                |--------------------------------------------------------------------------
                | Sản phẩm thường
                |--------------------------------------------------------------------------
                */
                else {

                    if (
                        $product->stock <
                        (int) $item['quantity']
                    ) {

                        throw new \Exception(
                            "Sản phẩm {$product->name} không đủ tồn kho"
                        );
                    }

                    $product->decrement(
                        'stock',
                        (int) $item['quantity']
                    );
                }

                /*
                |--------------------------------------------------
                | Cộng số lượng đã bán
                |--------------------------------------------------
                */
                $product->increment(
                    'sold_count',
                    (int) $item['quantity']
                );

                // Nếu có IMEI thì cập nhật trạng thái đã bán
                if (!empty($item['imei_id'])) {

                    $imei->update([

                        'status' =>
                            ProductImei::STATUS_SOLD,

                        'sold_at' => now(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Phát sinh Nợ mới
            |--------------------------------------------------------------------------
            */

            if (
                $customerId
                &&
                $paidAmount < $grandTotal
            ) {

                $newDebt =
                    $grandTotal - $paidAmount;

                CustomerDebt::query()
                    ->create([

                        'customer_id' =>
                            $customerId,

                        'type' =>
                            'increase',

                        'amount' =>
                            $newDebt,

                        'source_type' =>
                            Sale::class,

                        'source_id' =>
                            $sale->id,

                        'note' =>
                            'Nợ phát sinh từ hóa đơn',
                    ]);

                Customer::query()

                    ->where(
                        'id',
                        $customerId
                    )

                    ->increment(
                        'debt_balance',
                        $newDebt
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Xử lý nợ cũ
            |--------------------------------------------------------------------------
            */

            $debtPaid = 0;

            if (
                $payOldDebt
                &&
                $customerId
            ) {

                $customer = Customer::query()
                    ->find($customerId);

                if (
                    $customer
                    &&
                    $customer->debt_balance > 0
                ) {

                    $totalNeedToPay =
                        $grandTotal
                        +
                        $customer->debt_balance;

                    /*
                    |--------------------------------------------------
                    | Khách đưa chưa đủ
                    |--------------------------------------------------
                    */

                    if (
                        $paidAmount < $totalNeedToPay
                    ) {

                        $remainingDebt =
                            $totalNeedToPay
                            -
                            $paidAmount;

                        /*
                        |--------------------------------------------------
                        | Gán toàn bộ tiền cho hóa đơn mới trước
                        |--------------------------------------------------
                        */

                        $newDebt = max(
                            0,
                            $remainingDebt
                        );

                        if ($newDebt > 0) {

                            CustomerDebt::query()
                                ->create([

                                    'customer_id' =>
                                        $customerId,

                                    'type' =>
                                        'increase',

                                    'amount' =>
                                        $newDebt,

                                    'source_type' =>
                                        Sale::class,

                                    'source_id' =>
                                        $sale->id,

                                    'note' =>
                                        'Thiếu thanh toán',
                                ]);

                            $customer->increment(
                                'debt_balance',
                                $newDebt
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------
                    | Khách đưa dư để trả nợ cũ
                    |--------------------------------------------------
                    */

                    $extraMoney = max(
                        0,
                        $paidAmount - $grandTotal
                    );

                    $debtPaid = min(
                        $customer->debt_balance,
                        $extraMoney
                    );

                    if ($debtPaid > 0) {

                        CustomerDebt::query()
                            ->create([

                                'customer_id' =>
                                    $customer->id,

                                'type' =>
                                    'decrease',

                                'amount' =>
                                    $debtPaid,

                                'source_type' =>
                                    Sale::class,

                                'source_id' =>
                                    $sale->id,

                                'note' =>
                                    'Thanh toán Nợ cũ',
                            ]);

                        $customer->decrement(
                            'debt_balance',
                            $debtPaid
                        );
                    }
                }
            }

            // Tính tiền thừa
            $usedMoney = $grandTotal + $debtPaid;

        

            $changeMoney =
                max(
                    0,
                    $paidAmount - $usedMoney
                );

            $sale->update([

                'change_amount' =>
                    $changeMoney,
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
        return 'INV-' . now()->format(
            'YmdHis'
        );
    }
}