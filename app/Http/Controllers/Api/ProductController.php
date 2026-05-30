<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = $request->string('keyword')->toString();

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
                        ->orWhere('barcode', 'like', "%{$keyword}%");
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
            ->get([
                'id',
                'name',
                'sku',
                'barcode',
                'sell_price',
                'stock',
                'has_imei',
                'category_id',
            ]);

        return response()->json(

            $products->map(function ($product) {

               return [

                    'id' => $product->id,

                    'name' => $product->name,

                    'sku' => $product->sku,

                    'barcode' => $product->barcode,

                    'price' => $product->sell_price,

                    'stock' => $product->stock,

                    'has_imei' => $product->has_imei,

                    'category_id' => $product->category_id,
                ];
            })
        );
    }
}