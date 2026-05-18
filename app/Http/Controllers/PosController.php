<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'Pos/Index'
        );
    }

    public function scanImei(
        Request $request
    ) {

        $request->validate([
            'imei' => [
                'required',
            ],
        ]);

        $imei = ProductImei::query()

            ->with('product')

            ->where(
                'imei',
                $request->imei
            )

            ->first();

        if (! $imei) {

            return response()->json([
                'message' => 'IMEI không tồn tại',
            ], 404);
        }

        if ($imei->status === 'sold') {

            return response()->json([
                'message' => 'IMEI đã bán',
            ], 422);
        }

       return response()->json([

            'id' => $imei->id,

            'product_id' =>
                $imei->product->id,

            'imei' => $imei->imei,

            'product' => [

                'id' => $imei->product->id,

                'name' => $imei->product->name,

                'price' => $imei->product->sell_price,
            ],

            'product_id' => $imei->product->id,
        ]);
    }


    public function checkout(
        Request $request
    ) {

        $request->validate([

            'items' => [
                'required',
                'array',
            ],

            'customer_paid' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        DB::beginTransaction();

        try {

            $subtotal = collect(
                $request->items
            )->sum('price');

            $sale = Sale::query()

                ->create([

                    'code' =>
                        'HD-' . now()->format('YmdHis'),

                    'user_id' =>
                        auth()->id(),

                    'subtotal' =>
                        $subtotal,

                    'discount' => 0,

                    'total' =>
                        $subtotal,

                    'customer_paid' =>
                        $request->customer_paid,

                    'change_money' =>

                        $request->customer_paid
                        - $subtotal,
                ]);

            foreach (
                $request->items
                as $item
            ) {

                SaleItem::query()

                    ->create([

                        'sale_id' =>
                            $sale->id,

                        'product_id' =>
                            $item['product_id'],

                        'product_imei_id' =>
                            $item['id'],

                        'price' =>
                            $item['price'],

                        'qty' => 1,

                        'total' =>
                            $item['price'],
                    ]);

                ProductImei::query()

                    ->where(
                        'id',
                        $item['id']
                    )

                    ->update([
                        'status' => 'sold',
                    ]);

                Product::query()

                    ->where(
                        'id',
                        $item['product_id']
                    )

                    ->decrement('stock');
            }

            DB::commit();

            return response()->json([

                'success' => true,

                'sale_id' => $sale->id,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' =>
                    $e->getMessage(),
            ], 500);
        }
    }

}