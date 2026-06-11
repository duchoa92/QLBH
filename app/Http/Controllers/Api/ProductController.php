<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = $request->string('keyword')->toString();

                if (
            str_starts_with(
                strtolower($keyword),
                'imei/'
            )
        ) {

            $imeiKeyword = substr(
                $keyword,
                5
            );

            $productIds = ProductImei::query()

                ->where(
                    'status',
                    ProductImei::STATUS_AVAILABLE
                )

                ->where(
                    'imei',
                    'like',
                    "%{$imeiKeyword}%"
                )

                ->pluck('product_id');

            $products = Product::query()

                ->whereIn(
                    'id',
                    $productIds
                )

                ->where(
                    'is_active',
                    true
                )

                ->get()

                ->map(function ($product) {

                    return [

                        'id' => $product->id,

                        'name' => $product->name,

                        'sku' => $product->sku,

                        'barcode' => $product->barcode,

                        'price' => $product->sell_price,

                        'stock' => $product->stock,

                        'manage_stock_by_serial' => $product->manage_stock_by_serial,

                        'category_id' => $product->category_id,

                        'product_type' => $product->product_type,
                    ];
                });

            return response()->json(
                $products
            );
        }

        $categoryId = $request->input('category_id');

        $products = Product::query()

            /*
            |--------------------------------------------------
            | ACTIVE ONLY
            |--------------------------------------------------
            */
            ->where('is_active', true)

            /*
            |--------------------------------------------------
            | SEARCH
            |--------------------------------------------------
            */
            ->when($keyword, function ($query) use ($keyword) {

                $query->where(function ($sub) use ($keyword) {

                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('sku', 'like', "%{$keyword}%")
                        ->orWhere('barcode', 'like', "%{$keyword}%")
                        ->orWhereHas('imeis',
                            function ($q) use ($keyword) {

                                $q->where(
                                    'imei',
                                    'like',
                                    "%{$keyword}%"
                                );
                            }
                        );
                });
            })

            /*
            |--------------------------------------------------
            | CATEGORY FILTER
            |--------------------------------------------------
            */
            ->when($categoryId, function ($query) use ($categoryId) {

                $query->where('category_id', $categoryId);
            })

            /*
            |--------------------------------------------------
            | BEST SELLER FIRST
            |--------------------------------------------------
            */
            ->orderByDesc('sold_count')

            /*
            |--------------------------------------------------
            | NEWEST
            |--------------------------------------------------
            */
            ->orderByDesc('id')

            /*
            |--------------------------------------------------
            | LIMIT
            |--------------------------------------------------
            */
            ->limit(100)

            /*
            |--------------------------------------------------
            | SELECT OPTIMIZE
            |--------------------------------------------------
            */
            ->get()
            ->map(function ($product) {

                return [

                    'id' => $product->id,

                    'name' => $product->name,

                    'sku' => $product->sku,

                    'barcode' => $product->barcode,

                    'price' => $product->sell_price,

                    'stock' => $product->stock,

                    'manage_stock_by_serial' => $product->manage_stock_by_serial,

                    'category_id' => $product->category_id,

                    'product_type' => $product->product_type,
                ];
            });


        return response()->json(
            $products
        );
    }
}