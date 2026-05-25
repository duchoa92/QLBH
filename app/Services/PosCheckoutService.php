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

                'user_id' => $userId,

                'subtotal' => $subtotal,

                'discount' => $discount,

                'total' => $grandTotal,

                'customer_paid' => $paidAmount,

                'change_money' => $changeMoney,
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

                /*
                |--------------------------------------------------------------------------
                | Product
                |--------------------------------------------------------------------------
                */

                $product = Product::query()
                    ->findOrFail(
                        $item['id']
                    );

                /*
                |--------------------------------------------------------------------------
                | Trừ tồn kho
                |--------------------------------------------------------------------------
                */

                $product->decrement(
                    'stock',
                    (int) $item['quantity']
                );

                /*
                |--------------------------------------------------------------------------
                | IMEI
                |--------------------------------------------------------------------------
                */

                if (
                    ! empty(
                        $item['imei_id']
                    )
                ) {

                    ProductImei::query()

                        ->where(
                            'id',
                            $item['imei_id']
                        )

                        ->update([

                            'status' => 'sold',

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