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

            ->first();



        if ($imei) {

            if (
                $imei->status !== ProductImei::STATUS_AVAILABLE
            ) {

                    $message = match ($imei->status) {

                    ProductImei::STATUS_SOLD
                        => 'IMEI này đã được bán',

                    ProductImei::STATUS_REPAIRING
                        => 'IMEI này đang sửa chữa',

                    ProductImei::STATUS_RETURNED
                        => 'IMEI này đã được trả hàng',

                    default
                        => 'IMEI không khả dụng',
                };

                return response()->json([
                    'message' => 'IMEI này đã được bán'
                ], 422);
            }

            return response()->json([
                'type' => 'imei',

                'data' => [
                    'id' => $imei->product->id,
                    'name' => $imei->product->name,
                    'sell_price' => $imei->product->sell_price,
                    'image_url' => $imei->product->image_url,
                    'product_type' => 'imei',
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
            'message' => 'Sản phẩm không tồn tại'
        ], 404);
    }
}