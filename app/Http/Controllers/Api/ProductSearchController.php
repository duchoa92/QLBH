<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->get('q');

        $products = Product::query()

            ->when($q, function ($query) use ($q) {

                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            })

            ->where('is_active', true)

            ->limit(10)

            ->get([
                'id',
                'name',
                'sell_price',
                'barcode',
                'sku',
            ]);

        return response()->json($products);
    }
}