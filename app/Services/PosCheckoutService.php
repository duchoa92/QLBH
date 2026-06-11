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
            | Tính toán tổng tiền hóa đơn
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

            // Nếu không có khách hàng và tiền thanh toán chưa đủ thì báo lỗi
            if (
                !$customerId
                &&
                $paidAmount < $grandTotal
            ) {

                throw new \Exception(
                    'Khách lẻ phải thanh toán đủ tiền'
                );
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

                'change_amount' => $changeMoney,

                'payment_method' => $paymentMethod,
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
                    ->findOrFail(
                        $item['id']
                    );


                /*
                |--------------------------------------------------------------------------
                | Kiểm tra IMEI
                |--------------------------------------------------------------------------
                */

                $imei = null;


                if (
                    $product->product_type === 'imei'
                ) {

                    if (
                        empty($item['imei_id'])
                    ) {

                        throw new \Exception(
                            "Sản phẩm {$product->name} phải quét IMEI"
                        );
                    }


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
                | Kiểm tra tồn kho sản phẩm thường
                |--------------------------------------------------------------------------
                */

                if (
                    $product->product_type !== 'imei'
                ) {


                    if (
                        $product->stock <
                        (int) $item['quantity']
                    ) {

                        throw new \Exception(
                            "Sản phẩm {$product->name} không đủ tồn kho"
                        );
                    }
                }



                /*
                |--------------------------------------------------------------------------
                | Lưu chi tiết hóa đơn
                |--------------------------------------------------------------------------
                */

                $sale->items()->create([

                    'product_id' =>
                        $item['id'],

                    'product_imei_id' =>
                        $item['imei_id'] ?? null,

                    'quantity' =>
                        (int) $item['quantity'],

                    'unit_price' =>
                        (float) $item['price'],

                    'subtotal' =>
                        (
                            (float) $item['price']
                            *
                            (int) $item['quantity']
                        ),
                ]);



                /*
                |--------------------------------------------------------------------------
                | Trừ tồn kho sản phẩm thường
                |--------------------------------------------------------------------------
                */

                if (
                    $product->product_type !== 'imei'
                ) {

                    $product->decrement(
                        'stock',
                        (int) $item['quantity']
                    );
                }



                /*
                |--------------------------------------------------------------------------
                | Cộng số lượng đã bán
                |--------------------------------------------------------------------------
                */

                $product->increment(
                    'sold_count',
                    (int) $item['quantity']
                );



                /*
                |--------------------------------------------------------------------------
                | Đánh dấu IMEI đã bán
                |--------------------------------------------------------------------------
                */

                if ($imei) {

                    $imei->update([

                        'status' =>
                            ProductImei::STATUS_SOLD,

                        'sold_at' =>
                            now(),
                    ]);
                }

            }
            if (
                !$payOldDebt
                &&
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
                            'Thanh toán hóa đơn còn thiếu',
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

                    /*
                    |--------------------------------------------------------------------------
                    | Tiền dành cho hóa đơn mới
                    |--------------------------------------------------------------------------
                    */

                    $moneyForInvoice = min(
                        $paidAmount,
                        $grandTotal
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Thiếu tiền hóa đơn mới
                    |--------------------------------------------------------------------------
                    */

                    $newDebt = max(
                        0,
                        $grandTotal - $moneyForInvoice
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
                                    'Thanh toán hóa đơn còn thiếu',
                            ]);

                        $customer->increment(
                            'debt_balance',
                            $newDebt
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Tiền dư dùng để trả nợ cũ
                    |--------------------------------------------------------------------------
                    */

                    $moneyForOldDebt = max(
                        0,
                        $paidAmount - $grandTotal
                    );

                    $debtPaid = min(
                        $customer->debt_balance,
                        $moneyForOldDebt
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