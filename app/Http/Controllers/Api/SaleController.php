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

        $search = $request->get('search');

        $sales = Sale::query()

            ->with([
                'customer',
                'user',
                'items.product',
                'items.productImei',
            ])

            ->when(

                $search,

                function ($query) use ($search) {

                    $query

                        ->where(
                            'code',
                            'like',
                            "%{$search}%"
                        )

                        ->orWhereHas(

                            'customer',

                            function ($customer) use ($search) {

                                $customer

                                    ->where(
                                        'full_name',
                                        'like',
                                        "%{$search}%"
                                    )

                                    ->orWhere(
                                        'phone',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )

                        ->orWhereHas(

                            'items.product',

                            function ($product) use ($search) {

                                $product->where(

                                    'name',

                                    'like',

                                    "%{$search}%"
                                );
                            }
                        )

                        ->orWhereHas(

                            'items.productImei',

                            function ($imei) use ($search) {

                                $imei->where(

                                    'imei',

                                    'like',

                                    "%{$search}%"
                                );
                            }
                        );
                }
            )

            ->latest()

            ->paginate(10)

            ->withQueryString();


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