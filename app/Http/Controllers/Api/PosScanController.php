<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\Request;

class PosScanController extends Controller
{
    public function scan(Request $request)
    {
        $code = trim($request->code);

        /*
        |--------------------------------------------------
        | CHECK IMEI
        |--------------------------------------------------
        */

        $imei = ProductImei::query()

            ->with('product')

            ->where('imei', $code)

            ->where('status', ProductImei::STATUS_AVAILABLE)

            ->first();

        // Debug
        dd($code, $imei);

        if ($imei) {

            return response()->json([
                'type' => 'imei',

                'data' => [
                    'id' => $imei->product->id,
                    'name' => $imei->product->name,
                    'price' => $imei->product->sell_price,
                    'imei_id' => $imei->id,
                    'imei' => $imei->imei,
                ]
            ]);
        }

        /*
        |--------------------------------------------------
        | CHECK BARCODE
        |--------------------------------------------------
        */

        $product = Product::query()

            ->where('barcode', $code)

            ->where('is_active', true)

            ->first();

        if ($product) {

            return response()->json([
                'type' => 'barcode',

                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sell_price' => $product->sell_price,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Không tìm thấy sản phẩm'
        ], 404);
    }
}