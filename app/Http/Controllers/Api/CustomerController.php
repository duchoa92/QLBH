<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $service
    ) {}

    /** Check for another customer with the same normalized phone number. */
    public function checkPhone(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        $phone = trim($data['phone']);
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';
        $phoneVariants = [$digits];
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $phoneVariants[] = '84' . substr($digits, 1);
        } elseif (str_starts_with($digits, '84') && strlen($digits) >= 11) {
            $phoneVariants[] = '0' . substr($digits, 2);
        }
        $query = Customer::query()
            ->select(['id', 'code', 'full_name', 'phone', 'cccd', 'debt_balance'])
            ->where(function ($query) use ($phone, $digits, $phoneVariants) {
                $query->where('phone', $phone);
                if (strlen($digits) >= 7) {
                    $normalizedColumn = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), '.', ''), '/', '')";
                    $query->orWhereIn(DB::raw($normalizedColumn), array_unique($phoneVariants));
                }
            });

        if (!empty($data['exclude_id'])) {
            $query->where('id', '!=', $data['exclude_id']);
        }

        $total = (clone $query)->count();

        return response()->json([
            'customers' => $query->orderBy('full_name')->limit(10)->get(),
            'total' => $total,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Find existing customer records for a CCCD without exposing full identity data. */
    public function checkCccd(Request $request)
    {
        $data = $request->validate([
            'cccd' => ['required', 'string', 'regex:/^\\d{9,12}$/'],
            'exclude_id' => ['nullable', 'integer'],
        ], [
            'cccd.regex' => 'CCCD phải gồm 9 hoặc 12 chữ số.',
        ]);

        $cccd = Customer::normalizeCccd($data['cccd']);
        $query = Customer::query()
            ->select(['id', 'code', 'full_name', 'cccd'])
            ->where('cccd', $cccd);

        if (!empty($data['exclude_id'])) {
            $query->where('id', '!=', $data['exclude_id']);
        }

        $total = (clone $query)->count();

        return response()->json([
            'customers' => $query->orderBy('full_name')->limit(10)->get(),
            'total' => $total,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    /*
    |---------------------------------------
    | Tạo khách hàng (MODAL POS)
    |---------------------------------------
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
        ], [
            'cccd.regex' => 'CCCD phải gồm 9 hoặc 12 chữ số.',
            'cccd.unique' => 'Số CCCD này đã tồn tại trong hệ thống.',
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
                'cccd',
                'debt_balance',
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
                    )
                    ->orWhere(
                        'cccd',
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
