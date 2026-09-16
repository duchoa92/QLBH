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

            ->with([
                'product.unit:id,name,short_name',
                'variant:id,product_id,sku,barcode,attributes,cost_price,sell_price,stock',
            ])

            ->where('imei', $code)
            ->where(function ($query) {
                $query->whereNull('variant_id')
                    ->orWhereHas('variant', fn ($variantQuery) =>
                        $variantQuery->where('is_active', true)
                    );
            })

            ->first();



        if ($imei) {

            if (
                $imei->status !== ProductImei::STATUS_IN_STOCK
            ) {

                return response()->json([
                    'message' => 'IMEI này đã được bán'
                ], 422);
            }

            $effectiveSellPrice =
                $imei->sell_price > 0
                    ? (float) $imei->sell_price
                    : ($imei->variant?->sell_price > 0
                        ? (float) $imei->variant->sell_price
                        : (float) $imei->product->sell_price);

            return response()->json([
                'type' => 'imei',

                'data' => [
                    'id' => $imei->product->id,
                    'name' => $imei->product->name,
                    'sell_price' => $effectiveSellPrice,
                    'price' => $effectiveSellPrice,
                    'image_url' => $imei->product->image_url,
                    'product_type' => 'imei',
                    'unit_id' => $imei->product->unit_id,
                    'unit_name' => $imei->product->unit?->short_name ?: $imei->product->unit?->name,
                    'variant' => $imei->variant
                        ? [
                            'id' => $imei->variant->id,
                            'sku' => $imei->variant->sku,
                            'barcode' => $imei->variant->barcode,
                            'attributes' => $imei->variant->attributes,
                            'cost_price' => $imei->variant->cost_price,
                            'sell_price' => $imei->variant->sell_price,
                            'price' => $imei->variant->sell_price,
                            'stock' => $imei->variant->stock,
                        ]
                        : null,
                    'imei_id' => $imei->id,
                    'imei' => $imei->imei,
                    'serial' => $imei->serial,
                    'color' => $imei->color,
                    'storage' => $imei->storage,
                    'cost_price' => $imei->cost_price,
                    'imei_sell_price' => $imei->sell_price,
                ]
            ]);
        }

        /*
        |--------------------------------------------------
        | CHECK BARCODE
        |--------------------------------------------------
        */

        $product = Product::query()
            ->with([
                'unit:id,name,short_name',
            ])

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
                    'price' => $product->sell_price,
                    'unit_id' => $product->unit_id,
                    'unit_name' => $product->unit?->short_name ?: $product->unit?->name,
                ]
            ]);
        }

        return response()->json([
            'message' => 'Sản phẩm không tồn tại'
        ], 404);
    }
}
