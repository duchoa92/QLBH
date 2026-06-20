<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $service
    ) {}

    /*
    |---------------------------------------
    | Tạo khách hàng (MODAL POS)
    |---------------------------------------
    */
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = $this->service->create($data);

        return response()->json([
            'message' => 'created',
            'data' => $customer
        ]);
    }

    /*
    |---------------------------------------
    | SEARCH CUSTOMER (POS AUTOCOMPLETE)
    |---------------------------------------
    */
    public function search(Request $request)
    {
        $search = (string) $request->get(
            'search',
            ''
        );

        $keyword =
            Customer::normalizeSearch(
                $search
            );

        $customers = Customer::query()

            ->select([
                'id',
                'full_name',
                'phone',
            ])

            ->where(function ($q)
            use (
                $keyword,
                $search
            ) {

                $q

                    ->where(
                        'search_text',
                        'like',
                        "%{$keyword}%"
                    )

                    ->orWhere(
                        'phone',
                        'like',
                        "%{$search}%"
                    );
            })

            ->limit(10)

            ->get();

        return response()->json(
            $customers
        );
    }
    /*
    |---------------------------------------
    | Lấy danh sách Nợ của khách hàng
    |---------------------------------------
    */
    public function debts(
        Customer $customer
    )
    {
        $balance = 0;

        $debts = $customer
            ->debts()
            ->with('source')
            ->orderBy(
                'created_at',
                'asc'
            )
            ->get()
            ->map(function ($debt) use (&$balance) {

                if (
                    $debt->type === 'increase'
                ) {

                    $balance +=
                        (float) $debt->amount;
                } else {

                    $balance -=
                        (float) $debt->amount;
                }

                return [

                    'id' =>
                        $debt->id,

                    'type' =>
                        $debt->type,

                    'amount' =>
                        $debt->amount,

                    'balance' =>
                        $balance,

                    'note' =>
                        $debt->note,
                        
                    'sale_id' =>
                        $debt->source_id,

                    'source_id' =>
                        $debt->source_id,

                    'source_code' =>
                        $debt->source?->code,

                    'created_at' =>
                        $debt->created_at
                            ->format('d/m/Y H:i'),
                ];
            });

        $debts = $debts->reverse()->values();

        return response()->json([

            'debts' =>
                $debts,

            'total' =>
                $customer->debt_balance,
        ]);
    }


}