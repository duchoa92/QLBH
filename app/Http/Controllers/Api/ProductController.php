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

        $categoryId = $request->integer('category_id');

        $query = Product::query()
            ->where('is_active', true)

            /*
            |--------------------------------------------------
            | SEARCH
            |--------------------------------------------------
            */
            ->when($keyword, function ($q) use ($keyword) {

                $q->where(function ($sub) use ($keyword) {

                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('sku', 'like', "%{$keyword}%")
                        ->orWhere('barcode', 'like', "%{$keyword}%");
                });
            })

            /*
            |--------------------------------------------------
            | FILTER CATEGORY
            |--------------------------------------------------
            */
            ->when($categoryId, function ($q) use ($categoryId) {

                $q->where('category_id', $categoryId);
            })

            /*
            |--------------------------------------------------
            | ORDER BEST SELLER FIRST
            |--------------------------------------------------
            */
            ->orderByDesc('sold_count')

            /*
            |--------------------------------------------------
            | NEWEST
            |--------------------------------------------------
            */
            ->orderByDesc('id');

        $products = $query
            ->limit(100)
            ->get();

        return response()->json($products);
    }
}