<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(): Response
    {
        $sales = Sale::query()

            ->with([
                'items.product',
            ])

            ->when(

                request('search'),

                function (
                    $query
                ) {

                    $query->where(

                        'code',

                        'like',

                        '%' . request('search') . '%'
                    )

                    ->orWhereHas(

                        'items',

                        function ($itemQuery) {

                            $itemQuery->whereHas(

                                'productImei',

                                function (
                                    $imeiQuery
                                ) {

                                    $imeiQuery->where(

                                        'imei',

                                        'like',

                                        '%' . request('search') . '%'
                                    );
                                }
                            );
                        }
                    );
                }
            )

            ->latest()

            ->paginate(10)

            ->withQueryString();

        return Inertia::render(

            'Sales/Index',

            [
                'sales' => $sales,
                'filters' => [
                    'search' =>
                        request('search'),
                ],
            ]
        );
    }

    // Hiển thị chi tiết đơn hàng
    public function show(
        Sale $sale
    ): Response {

        $sale->load([

            'items.product',
            'items.productImei',
        ]);

        return Inertia::render(

            'Sales/Show',

            [
                'sale' => $sale,
            ]
        );
    }

    // Hiển thị hóa đơn
    public function receipt(
        Sale $sale
    ) {

        $sale->load([
            'items.product',
            'items.productImei',
        ]);

        return inertia(
            'Sales/Receipt',
            [

                'sale' => $sale,
            ]
        );
    }
}