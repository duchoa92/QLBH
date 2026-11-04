<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\CustomerDebt;
use Inertia\Inertia;
use Inertia\Response;

class SaleReceiptController extends Controller
{
    public function show(
        Sale $sale
    ): Response {

        $sale->load([

            'items.product',

            'items.variant',

            'items.productImei',
            
            'items.gifts.product',

            'customer',

            'user',
        ]);

        $sale->setAttribute('old_debt_paid', (float) CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'decrease')
            ->where('note', 'like', 'Trích tiền thừa hóa đơn%')
            ->sum('amount'));

        return Inertia::render(
            'Sales/Receipt',
            [
                'sale' => $sale,
            ]
        );
    }
}
