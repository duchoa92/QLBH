<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /*
    |--------------------------------------------------
    | INDEX
    |--------------------------------------------------
    */
    public function index(Request $request): Response
    {
        $search = (string) $request->get('search', '');

        $customers = Customer::query()
            ->select([
                'id',
                'code',
                'full_name',
                'phone',
                'point_balance',
                'debt_balance',
                'customer_type',
                'is_active',
                'created_at',
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");

                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /*
    |--------------------------------------------------
    | CREATE
    |--------------------------------------------------
    */
    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    /*
    |--------------------------------------------------
    | STORE
    |--------------------------------------------------
    */
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birthday' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'cccd' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
        ]);

        Customer::create([
            ...$data,

            'code' => 'KH' . str_pad(
                (string) (Customer::max('id') + 1),
                6,
                '0',
                STR_PAD_LEFT
            ),

            'customer_type' => 'retail',
            'is_active' => true,
        ]);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Tạo khách hàng thành công');
    }
}