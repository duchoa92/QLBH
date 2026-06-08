<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImei;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class PosCheckoutService
{
    public function checkout(
        array $items,
        ?int $customerId,
        float $paidAmount,
        string $paymentMethod,
        int $userId,
    ): Sale {

        return DB::transaction(function () use (
            $items,
            $customerId,
            $paidAmount,
            $paymentMethod,
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

            $changeMoney =
                $paidAmount - $grandTotal;

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

                'customer_paid' => $paidAmount,

                'change_money' => $changeMoney,

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
                | IMEI PRODUCT MUST HAVE IMEI
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

                    ProductImei::query()

                        ->where(
                            'id',
                            $item['imei_id']
                        )

                        ->update([

                            'status' =>
                                ProductImei::STATUS_SOLD,

                            'sold_at' => now(),
                        ]);
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

                // 
                if (!empty($item['imei_id'])) {

                    $imei->update([

                        'status' =>
                            ProductImei::STATUS_SOLD,

                        'sold_at' => now(),
                    ]);
                }
            }

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