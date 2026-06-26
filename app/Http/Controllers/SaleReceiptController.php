<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class SaleReceiptController extends Controller
{
    public function show(
        Sale $sale
    ): Response {

        $sale->load([

            'items.product',

            'items.productImei',
            
            'items.gifts.product',

            'customer',

            'user',
        ]);

        return Inertia::render(
            'Sales/Receipt',
            [
                'sale' => $sale,
            ]
        );
    }
}