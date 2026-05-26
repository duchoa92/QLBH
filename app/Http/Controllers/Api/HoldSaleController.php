<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HoldSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoldSaleController
    extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Save Hold
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): JsonResponse {

        // dư liệu hợp lệ
        $request->validate([

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'grand_total' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);




        $holdSale =
            HoldSale::query()
                ->create([

                    'user_id' =>
                        auth()->id(),

                    'customer_id' =>
                        $request->customer_id,

                    'name' =>
                        $request->name,

                    'cart_items' =>
                        $request->items,

                    'grand_total' =>
                        $request->grand_total,
                ]);

        return response()->json([

            'message' =>
                'Đã giữ hóa đơn',

            'data' =>
                $holdSale,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | List Hold
    |--------------------------------------------------------------------------
    */

    public function index(): JsonResponse
    {
        $holds = HoldSale::query()

            ->where(
                'user_id',
                auth()->id()
            )

            ->latest()

            ->get();

        return response()->json(
            $holds
        );
    }

    // Xem chi tiết hóa đơn giữ
    public function show(
        HoldSale $holdSale
    ): JsonResponse {

        return response()->json([

            'data' => $holdSale->load(
            'customer'
        ),
        ]);
    }

     /*
    |--------------------------------------------------------------------------
    | Xóa Hóa Đơn Giữ
    |--------------------------------------------------------------------------
    */

    public function destroy(
    int $holdSale
): JsonResponse {

    $deleted = HoldSale::query()

        ->where('id', $holdSale)

        ->delete();

    return response()->json([

        'deleted' => $deleted,

        'id' => $holdSale,
    ]);
}

}