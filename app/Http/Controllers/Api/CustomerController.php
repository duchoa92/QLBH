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
            ->get();

        return response()->json($customers);
    }

    /*
    |---------------------------------------
    | Lấy danh sách công nợ của khách hàng
    |---------------------------------------
    */
    public function debts(
        Customer $customer
    )
    {
        $debts = $customer
            ->debts()
            ->latest()
            ->get();

        return response()->json([
            'debts' => $debts,
            'total' => $customer->debt_balance,
        ]);
    }
}