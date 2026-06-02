<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ProductImei;

class ProductApiController extends Controller
{
    public function findByBarcode(
        Request $request
    ): JsonResponse {

        $barcode = (string) $request->get(
            'barcode'
        );

        $product = Product::query()

            ->with('imeis')

            ->where(
                'barcode',
                $barcode
            )

            ->orWhere(
                'sku',
                $barcode
            )

            ->first();

        $imei = null;

        if (! $product) {

            $imei = ProductImei::query()

                ->where(
                    'imei',
                    $barcode
                )

                ->first();

                
            if ($imei) {

                if (
                    $imei->status !==
                    ProductImei::STATUS_AVAILABLE
                ) {

                    return response()->json([
                        'message' => 'IMEI không khả dụng hoặc đã bán',
                    ], 422);
                }

                $product = Product::find(
                    $imei->product_id
                );
            }
        }

        
        if (! $product) {

            return response()->json([
                'message' => 'Không tìm thấy sản phẩm',
            ], 404);
        }

        return response()->json([

            'id' => $product->id,

            'name' => $product->name,

            'sku' => $product->sku,

            'barcode' => $product->barcode,

            'price' => $imei && $imei->sell_price > 0
                ? $imei->sell_price
                : $product->sell_price,

            'stock' => $product->stock,

            'imei_id' =>
                $imei?->id,

            'imei' =>
                $imei?->imei,

            'serial' =>
                $imei?->serial,

            'color' =>
                $imei?->color,

            'storage' =>
                $imei?->storage,
        ]);
    }
}