<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\CustomerImageService;

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

            'province' => ['nullable', 'string'],

            'district' => ['nullable', 'string'],

            'ward' => ['nullable', 'string'],

            'address' => ['nullable', 'string'],

            'note' => ['nullable', 'string'],

            /*
            |--------------------------------------------------
            | IMAGES
            |--------------------------------------------------
            */
            'images.*' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

        ]);

        /*
        |--------------------------------------------------
        | Tạo khách hàng
        |--------------------------------------------------
        */
        $customer = Customer::create([

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

        /*
        |--------------------------------------------------
        | UPLOAD IMAGES
        |--------------------------------------------------
        */
        if ($request->hasFile('images')) {

            $imageService = app(CustomerImageService::class);

            foreach ($request->file('images') as $index => $image) {

                $imageService->upload(
                    customer: $customer,
                    file: $image,
                    isPrimary: $index === 0
                );
            }
        }

        return redirect()
            ->route('customers.index')
            ->with('success', 'Tạo khách hàng thành công');
    }

    /*
    |--------------------------------------------------
    | Khách hàng
    |--------------------------------------------------
    */


    // Sửa khách hàng
    public function edit(Customer $customer): Response
    {
        $customer->load([
            'images'
        ]);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }


    // Cập nhật khách hàng
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'birthday' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'cccd' => ['nullable', 'string'],
            'province' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'ward' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);

        $customer->update($data);

        return redirect()
            ->route('customers.index')
            ->with('success', 'Cập nhật khách hàng thành công');
    }

    // Xóa khách hàng


    // Hiển thị chi tiết khách hàng
    public function show(Customer $customer): Response
    {
        $customer->load([
            'images',
            'devices',
            'points',
            'debts',
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }


    // Tìm kiếm khách hàng (API)
    public function search(Request $request)
    {
        $search = $request->get('search');

        $customers = Customer::query()
            ->select(['id', 'full_name', 'phone']) // Chỉ lấy những trường cần thiết
            ->withSum(['sales as total_debt' => function ($query) {
                $query->where('status', 'unpaid'); // Giả định status 'unpaid' là nợ
            }], 'remaining_amount') // Cột chứa số tiền còn lại phải trả
            ->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

}