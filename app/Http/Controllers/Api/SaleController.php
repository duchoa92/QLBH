<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Danh sách hóa đơn
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {

        $sales = Sale::query()

            ->with([
                'customer',
                'user',
            ])

            ->latest()

            ->limit(50)

            ->get();


        return response()->json(
            $sales
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Chi tiết hóa đơn
    |--------------------------------------------------------------------------
    */

    public function show(
        Sale $sale
    ) {


        $sale->load([

            'customer',

            'user',

            'items.product',

            'items.productImei',

        ]);


        return response()->json(
            $sale
        );
    }

}