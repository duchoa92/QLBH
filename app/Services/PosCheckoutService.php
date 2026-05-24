<?php

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
        ) {

            /*
            |--------------------------------------------------------------------------
            | Totals
            |--------------------------------------------------------------------------
            */

            $subtotal = collect($items)
                ->sum('subtotal');

            $discount = 0;

            $tax = 0;

            $grandTotal =
                $subtotal -
                $discount +
                $tax;

            $changeAmount =
                $paidAmount -
                $grandTotal;

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

                'tax' => $tax,

                'grand_total' => $grandTotal,

                'paid_amount' => $paidAmount,

                'change_amount' => $changeAmount,

                'payment_method' => $paymentMethod,

                'status' => 'completed',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Sale Items
            |--------------------------------------------------------------------------
            */

            foreach ($items as $item) {

                $sale->items()->create([

                    'product_id' => $item['id'],

                    'product_imei_id' =>
                        $item['product_imei_id'],

                    'quantity' => $item['quantity'],

                    'unit_price' => $item['sell_price'],

                    'discount' => 0,

                    'tax' => 0,

                    'subtotal' => $item['subtotal'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | Product
                |--------------------------------------------------------------------------
                */

                $product = Product::query()
                    ->findOrFail($item['id']);

                /*
                |--------------------------------------------------------------------------
                | Deduct Stock
                |--------------------------------------------------------------------------
                */

                $product->decrement(
                    'stock',
                    $item['quantity']
                );

                /*
                |--------------------------------------------------------------------------
                | IMEI Product
                |--------------------------------------------------------------------------
                */

                if ($item['product_imei_id']) {

                    ProductImei::query()

                        ->where(
                            'id',
                            $item['product_imei_id']
                        )

                        ->update([

                            'status' =>
                                ProductImei::STATUS_SOLD,

                            'sale_id' => $sale->id,

                            'customer_id' =>
                                $customerId,

                            'sold_at' => now(),
                        ]);
                }
            }

            return $sale;
        });
    }

    /**
     * Generate invoice code.
     */
    private function generateCode(): string
    {
        return 'INV-' . now()->format('YmdHis');
    }
}