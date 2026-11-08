<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\CustomerImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $debt = in_array($request->query('debt'), ['owing', 'clear'], true) ? $request->query('debt') : '';
        $status = in_array($request->query('status'), ['active', 'blocked'], true) ? $request->query('status') : '';
        $points = in_array($request->query('points'), ['has', 'none'], true) ? $request->query('points') : '';
        $dateFrom = $this->validDate($request->query('date_from'));
        $dateTo = $this->validDate($request->query('date_to'));
        $perPage = in_array((int) $request->query('per_page', 20), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 20) : 20;
        $sortBy = in_array($request->query('sort_by'), ['code', 'full_name', 'phone', 'point_balance', 'debt_balance', 'customer_type'], true)
            ? $request->query('sort_by') : 'created_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $customers = Customer::query()
            ->select([
                'id',
                'code',
                'full_name',
                'phone',
                'birthday',
                'gender',
                'cccd',
                'province',
                'district',
                'ward',
                'address',
                'note',
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
                        ->orWhere('cccd', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");

                });
            })
            ->when($debt === 'owing', fn ($query) => $query->where('debt_balance', '>', 0))
            ->when($debt === 'clear', fn ($query) => $query->where(
                fn ($sub) => $sub->whereNull('debt_balance')->orWhere('debt_balance', '<=', 0)
            ))
            ->when($status === 'active', fn ($query) => $query->where(
                fn ($sub) => $sub->where('is_active', true)->orWhereNull('is_active')
            ))
            ->when($status === 'blocked', fn ($query) => $query->where('is_active', false))
            ->when($points === 'has', fn ($query) => $query->where('point_balance', '>', 0))
            ->when($points === 'none', fn ($query) => $query->where(
                fn ($sub) => $sub->whereNull('point_balance')->orWhere('point_balance', '<=', 0)
            ))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->withCount(['sales', 'repairs', 'debts', 'points'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage)
            ->withQueryString();

        $customers->through(function (Customer $customer) {
            $customer->setAttribute('has_transactions',
                ($customer->sales_count + $customer->repairs_count + $customer->debts_count + $customer->points_count) > 0
                || abs((float) $customer->debt_balance) > 0
                || abs((float) $customer->point_balance) > 0
            );

            return $customer;
        });

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'debt' => $debt,
                'status' => $status,
                'points' => $points,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Chỉ nhận ngày dạng YYYY-MM-DD, giá trị lạ bị bỏ qua thay vì gây lỗi 500. */
    private function validDate(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
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
        $request->merge(['cccd' => Customer::normalizeCccd($request->input('cccd'))]);
        $data = $request->validate([

            'full_name' => ['required', 'string', 'max:255'],

            'phone' => ['nullable', 'string', 'max:20'],

            'birthday' => ['nullable', 'date'],

            'gender' => ['nullable', 'string'],

            'cccd' => ['nullable', 'string', 'regex:/^\\d{9,12}$/', Rule::unique('customers', 'cccd')],

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

        ], [
            'cccd.regex' => 'CCCD phải gồm 9 hoặc 12 chữ số.',
            'cccd.unique' => 'Số CCCD này đã tồn tại trong hệ thống.',
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

        return back()
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
        $request->merge(['cccd' => Customer::normalizeCccd($request->input('cccd'))]);
        $data = $request->validate([
            'full_name' => ['required', 'string'],
            'phone' => ['nullable', 'string'],
            'birthday' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'cccd' => ['nullable', 'string', 'regex:/^\\d{9,12}$/', Rule::unique('customers', 'cccd')->ignore($customer->id)],
            'province' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'ward' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ], [
            'cccd.regex' => 'CCCD phải gồm 9 hoặc 12 chữ số.',
            'cccd.unique' => 'Số CCCD này đã tồn tại trong hệ thống.',
        ]);

        $customer->update($data);

        return back()
            ->with('success', 'Cập nhật khách hàng thành công');
    }

    // Xóa khách hàng: chỉ cho xóa khi chưa có bất kỳ phát sinh nào
    public function destroy(Customer $customer)
    {
        $usage = $customer->usageSummary();

        if ($usage['total'] > 0) {
            $parts = array_filter([
                $usage['sales'] ? "{$usage['sales']} hóa đơn" : null,
                $usage['repairs'] ? "{$usage['repairs']} phiếu sửa chữa" : null,
                $usage['debts'] ? "{$usage['debts']} giao dịch công nợ" : null,
                $usage['points'] ? "{$usage['points']} giao dịch điểm" : null,
            ]);
            $detail = $parts ? ' (' . implode(', ', $parts) . ')' : ' (còn số dư công nợ/điểm)';

            throw ValidationException::withMessages([
                'customer' => "Khách hàng {$customer->full_name} đã có phát sinh{$detail} nên không thể xóa. Hãy dùng chức năng Chặn để ngừng giao dịch.",
            ]);
        }

        DB::transaction(function () use ($customer) {
            // Giải phóng SĐT/CCCD (có ràng buộc duy nhất) để có thể tạo lại khách sau này.
            $customer->forceFill(['phone' => null, 'cccd' => null, 'is_active' => false])->save();
            $customer->delete();
        });

        return back()->with('success', 'Đã xóa khách hàng');
    }

    // Chặn / bỏ chặn khách hàng (không cho phát sinh giao dịch mới)
    public function toggleBlock(Customer $customer)
    {
        $block = ! $customer->isBlocked();
        $customer->update(['is_active' => ! $block]);

        return back()->with('success', $block ? 'Đã chặn khách hàng' : 'Đã bỏ chặn khách hàng');
    }


    // Hiển thị chi tiết khách hàng
    public function show(Customer $customer): Response
    {
        $customer->load([
            'images',
            'devices',
            'points' => fn ($query) => $query->latest(),
            'debts',
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }


    // Tìm kiếm khách hàng (API)
    public function search(Request $request)
    {
        $search = (string) $request->get('search', '');

        $keyword = Customer::normalizeVietnamese(
            $search
        );

        $customers = Customer::query()

            ->select([
                'id',
                'code',
                'full_name',
                'phone',
                'cccd',
            ])

            ->withSum([
                'sales as debt_balance' => function ($query) {
                    $query->where('status', 'unpaid');
                },
            ], 'remaining_amount')

            ->withSum([
                'sales as total_debt' => function ($query) {
                    $query->where('status', 'unpaid');
                }
            ], 'remaining_amount')

            ->where(function ($q) use ($keyword, $search) {

                $q->where(
                    'phone',
                    'like',
                    "%{$search}%"
                )

                ->orWhere(
                    'code',
                    'like',
                    "%{$search}%"
                )

                ->orWhere('cccd', 'like', "%{$search}%")

                ->orWhere(
                    'search_text',
                    'like',
                    "%{$keyword}%"
                );
            })

            ->notBlocked()

            ->limit(10)

            ->get();

        return response()->json($customers);
    }

}
