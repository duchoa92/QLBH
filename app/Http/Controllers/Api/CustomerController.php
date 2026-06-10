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
        $q = $request->get('q');

        $customers = Customer::query()
            ->when($q, function ($query) use ($q) {
                $query->where('phone', 'like', "%{$q}%")
                      ->orWhere('full_name', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get()
            ->map(function (
            Customer $customer
        ) {

            return [

                'id' =>
                    $customer->id,

                'full_name' =>
                    $customer->full_name,

                'phone' =>
                    $customer->phone,

                'debt_balance' =>
                    $customer->debt_balance,
            ];
        });

        return response()->json($customers);
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
        $debts = $customer
            ->debts()
            ->latest()
            ->get()
            ->map(function ($debt) {

                return [

                    'id' =>
                        $debt->id,

                    'type' =>
                        $debt->type,

                    'amount' =>
                        $debt->amount,

                    'note' =>
                        $debt->note,

                    'source_id' =>
                        $debt->source_id,

                    'created_at' =>
                        $debt->created_at
                            ->format('d/m/Y H:i'),
                ];
            });

        return response()->json([

            'debts' =>
                $debts,

            'total' =>
                $customer->debt_balance,
        ]);
    }


}