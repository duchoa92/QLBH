<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDebt;
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
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:completed,cancelled,pending'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [10, 20, 50], true)) $perPage = 10;

        $sales = Sale::query()

            ->with([
                'customer',
                'user',
                'items.product.conversionUnit',
                'items.variant',
                'items.productImei',
            ])

            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['completed', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($status === 'pending', fn ($query) => $query->whereIn('status', ['pending', 'waiting']))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))

            ->latest()

            ->paginate($perPage)

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

            'items.product.conversionUnit',

            'items.variant',

            'items.productImei',
            
            'items.gifts.product',

        ]);

        $sale->setAttribute('old_debt_paid', (float) CustomerDebt::query()
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('type', 'decrease')
            ->where('note', 'like', 'Trích tiền thừa hóa đơn%')
            ->sum('amount'));


        return response()->json(
            $sale
        );
    }

}
