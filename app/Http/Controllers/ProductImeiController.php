<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImei;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductImeiController extends Controller
{
    public function index(
        Product $product
    ): Response {

        $product->load('imeis');

        return Inertia::render(

            'ProductImeis/Index',

            [
                'product' => $product,
            ]
        );
    }

    public function store(
        Request $request,
        Product $product
    ): RedirectResponse {

        $request->validate([

            'imei' => [

                'required',

                'unique:product_imeis,imei',
            ],
        ]);

        ProductImei::query()

            ->create([

                'product_id' =>
                    $product->id,

                'imei' =>
                    $request->imei,

                'status' =>
                    ProductImei::STATUS_AVAILABLE,
            ]);

        $product->increment('stock');

        return back();
    }


    // Hiển thị chi tiết IMEI
    public function show(
        ProductImei $imei
    ): Response {

        $imei->load([

            'product',

            'saleItems.sale.customer',
        ]);

        return Inertia::render(

            'ProductImeis/Show',

            [
                'imei' => $imei,
            ]
        );
    }

}