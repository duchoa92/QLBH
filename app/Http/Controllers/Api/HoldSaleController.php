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

            ->latest()

            ->get();

        return response()->json(
            $holds
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Hold
    |--------------------------------------------------------------------------
    */

    public function destroy(
        HoldSale $holdSale
    ): JsonResponse {

        $holdSale->delete();

        return response()->json([

            'message' =>
                'Đã xóa hóa đơn giữ',
        ]);
    }

    // Xem chi tiết hóa đơn giữ
    public function show(
        HoldSale $holdSale
    ): JsonResponse {

        return response()->json([

            'data' => $holdSale,
        ]);
    }
}