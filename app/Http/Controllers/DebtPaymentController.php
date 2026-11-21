<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDebt;
use App\Models\Supplier;
use App\Models\SupplierDebt;
use App\Models\Sale;
use App\Models\StockImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DebtPaymentController extends Controller
{
    public function customers(Request $request): Response
    {
        return $this->index($request, 'customer');
    }

    public function suppliers(Request $request): Response
    {
        return $this->index($request, 'supplier');
    }

    private function index(Request $request, string $entity): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10;
        // Mức nợ: [từ (không gồm), đến (gồm)] — null nghĩa là không giới hạn.
        $levels = [
            'lt1m' => [0, 1_000_000],
            '1m_5m' => [1_000_000, 5_000_000],
            '5m_20m' => [5_000_000, 20_000_000],
            'gt20m' => [20_000_000, null],
        ];
        $level = array_key_exists((string) $request->query('level'), $levels) ? (string) $request->query('level') : '';
        $isCustomer = $entity === 'customer';
        $model = $isCustomer ? Customer::query() : Supplier::query();
        $nameColumn = $isCustomer ? 'full_name' : 'name';

        $rows = $model->where('debt_balance', '>', 0)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where($nameColumn, 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($level !== '', function ($query) use ($levels, $level) {
                [$min, $max] = $levels[$level];
                $query->where('debt_balance', '>', $min);
                if ($max !== null) {
                    $query->where('debt_balance', '<=', $max);
                }
            })
            ->orderByDesc('debt_balance')
            ->paginate($perPage)->withQueryString();

        $partyIds = collect($rows->items())->pluck('id');
        $orders = $isCustomer
            ? Sale::query()->with('customer:id,full_name,code,phone')->whereIn('customer_id', $partyIds)->where('status', 'completed')
                ->whereRaw('grand_total > paid_amount')->orderBy('created_at')->get()
                ->map(fn (Sale $sale) => [
                    'id' => $sale->id, 'code' => $sale->code, 'party_id' => $sale->customer_id,
                    'party_name' => $sale->customer?->full_name, 'date' => $sale->created_at?->format('d/m/Y H:i'),
                    'total' => (float) $sale->grand_total, 'paid' => (float) $sale->paid_amount,
                    'due' => max(0, (float) $sale->grand_total - (float) $sale->paid_amount), 'href' => route('sales.index', ['open_sale' => $sale->id], false),
                ])->values()
            : StockImport::query()->with('supplier:id,name,code,phone')->whereIn('supplier_id', $partyIds)
                ->whereRaw('grand_total > paid_amount')->orderBy('import_date')->get()
                ->map(fn (StockImport $import) => [
                    'id' => $import->id, 'code' => $import->code, 'party_id' => $import->supplier_id,
                    'party_name' => $import->supplier?->name, 'date' => $import->import_date?->format('d/m/Y H:i'),
                    'total' => (float) $import->grand_total, 'paid' => (float) $import->paid_amount,
                    'due' => max(0, (float) $import->grand_total - (float) $import->paid_amount), 'href' => route('stock.show', $import),
                ])->values();

        $history = $this->history($isCustomer);

        return Inertia::render('Debts/Index', [
            'entity' => $entity,
            'rows' => $rows,
            'filters' => ['search' => $search, 'level' => $level, 'per_page' => $perPage],
            'summary' => [
                'total' => (float) ($isCustomer ? Customer::sum('debt_balance') : Supplier::sum('debt_balance')),
                'count' => (int) ($isCustomer ? Customer::where('debt_balance', '>', 0)->count() : Supplier::where('debt_balance', '>', 0)->count()),
            ],
            'orders' => $orders,
            'history' => $history,
            'bank' => $this->bankSettings(),
        ]);
    }

    /**
     * Cài đặt ngân hàng để tạo mã QR khi thu/chi công nợ (không gửi các khóa API/bí mật xuống trình duyệt).
     */
    private function bankSettings(): array
    {
        $accounts = collect(json_decode((string) setting('bank_accounts', '[]'), true) ?: [])
            ->map(function (array $account): array {
                unset($account['payment_api_key'], $account['payment_api_access_key'], $account['payment_api_secret_key'], $account['payment_webhook_secret']);

                return $account;
            })->values()->all();

        if (! $accounts && setting('bank_bin', '') && setting('bank_account', '')) {
            $accounts = [[
                'id' => 'legacy-default',
                'bank_bin' => setting('bank_bin', ''),
                'bank_name' => '',
                'bank_account' => setting('bank_account', ''),
                'bank_account_name' => setting('bank_account_name', ''),
                'is_default' => true,
            ]];
        }

        return [
            'accounts' => $accounts,
            'qr_theme' => setting('bank_qr_theme', 'classic'),
        ];
    }

    /**
     * Thông tin tổng của một lần thanh toán, ghi chung vào mọi dòng cùng payment_group.
     */
    private function paymentMeta(array $data, float $amount, float $discount, float $debtBefore): array
    {
        $received = isset($data['received']) ? round((float) $data['received'], 2) : $amount;
        $baseDue = isset($data['base_due']) ? round((float) $data['base_due'], 2) : round($amount + $discount, 2);
        $payable = max(0, round($baseDue - $discount, 2));

        return [
            'payment_group' => (string) Str::uuid(),
            'received_amount' => $received,
            'change_amount' => round(max(0, $received - $payable), 2),
            'shortage_amount' => round(max(0, $payable - $amount), 2),
            'debt_before' => round($debtBefore, 2),
            'debt_after' => round(max(0, $debtBefore - $amount - $discount), 2),
        ];
    }

    /**
     * Lịch sử thanh toán, mỗi phần tử là MỘT lần thanh toán (gộp các dòng theo từng hóa đơn / phiếu và dòng giảm trừ).
     * Giao dịch cũ chưa có payment_group được gộp theo đối tượng + thời điểm ghi.
     */
    private function history(bool $isCustomer): array
    {
        $rows = $isCustomer
            ? CustomerDebt::query()->with(['customer:id,full_name,code', 'source', 'user:id,name'])
                ->where('type', 'decrease')->whereNotNull('payment_method')->orderByDesc('id')->limit(600)->get()
            : SupplierDebt::query()->with(['supplier:id,name,code', 'source', 'user:id,name'])
                ->where('type', 'decrease')->orderByDesc('id')->limit(600)->get();

        return $rows
            ->groupBy(fn ($row) => $row->payment_group ?: 'legacy:'.($isCustomer ? $row->customer_id : $row->supplier_id).':'.$row->created_at?->timestamp)
            ->take(100)
            ->map(function ($group) use ($isCustomer) {
                $first = $group->first();
                $party = $isCustomer ? $first->customer : $first->supplier;
                $cashRows = $group->where('payment_method', '!=', 'discount');
                $discountRows = $group->where('payment_method', 'discount');

                $orders = $group->groupBy(fn ($row) => $row->source_id ?: 0)->map(function ($items) {
                    $source = $items->first()->source;

                    return [
                        'id' => $items->first()->source_id,
                        'code' => $source?->code,
                        'total' => $source ? (float) $source->grand_total : null,
                        'amount' => (float) $items->where('payment_method', '!=', 'discount')->sum('amount'),
                        'discount' => (float) $items->where('payment_method', 'discount')->sum('amount'),
                    ];
                })->sortBy(fn ($order) => $order['id'] ? 0 : 1)->values();

                $linked = $orders->filter(fn ($order) => $order['id']);
                $note = $cashRows->first()?->note
                    ?? trim(str_replace('Giảm trừ công nợ.', '', (string) $discountRows->first()?->note));

                return [
                    'id' => $first->id,
                    'party_id' => $isCustomer ? $first->customer_id : $first->supplier_id,
                    'party' => $isCustomer ? $party?->full_name : $party?->name,
                    'party_code' => $party?->code,
                    'code' => $linked->first()['code'] ?? null,
                    'source_id' => $linked->first()['id'] ?? null,
                    'order_count' => $linked->count(),
                    'orders' => $orders,
                    'amount' => (float) $cashRows->sum('amount'),
                    'discount' => (float) $discountRows->sum('amount'),
                    'method' => $cashRows->first()?->payment_method ?? 'discount',
                    'note' => $note ?: null,
                    'date' => $first->created_at?->format('d/m/Y H:i'),
                    'date_full' => $first->created_at?->format('d/m/Y H:i:s'),
                    'user' => $first->user?->name,
                    'has_detail' => (bool) $first->payment_group,
                    'received' => $first->received_amount !== null ? (float) $first->received_amount : null,
                    'change' => $first->change_amount !== null ? (float) $first->change_amount : null,
                    'shortage' => $first->shortage_amount !== null ? (float) $first->shortage_amount : null,
                    'debt_before' => $first->debt_before !== null ? (float) $first->debt_before : null,
                    'debt_after' => $first->debt_after !== null ? (float) $first->debt_after : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Gộp các dòng chia tiền theo hóa đơn/phiếu: [orderId => ['amount' => thu/chi, 'discount' => giảm trừ]].
     * Tương thích kiểu cũ: chỉ gửi order_id => toàn bộ cho đơn đó.
     */
    private function normalizeAllocations(array $data, float $amount, float $discount): \Illuminate\Support\Collection
    {
        $rows = collect($data['allocations'] ?? []);
        if ($rows->isEmpty() && ! empty($data['order_id'])) {
            $rows = collect([['order_id' => $data['order_id'], 'amount' => $amount, 'discount' => $discount]]);
        }

        return $rows
            ->groupBy(fn ($row) => (int) $row['order_id'])
            ->map(fn ($group) => [
                'amount' => round((float) $group->sum('amount'), 2),
                'discount' => round((float) $group->sum(fn ($row) => (float) ($row['discount'] ?? 0)), 2),
            ])
            ->filter(fn (array $row) => $row['amount'] + $row['discount'] > 0);
    }

    private function paymentRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            // Giảm trừ / chiết khấu: giảm nợ nhưng không phát sinh thu/chi tiền
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'note' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer'],
            // Chỉ để lưu lịch sử: tiền khách đưa / tổng nợ đã chọn (thừa, thiếu được tính lại ở server)
            'received' => ['nullable', 'numeric', 'min:0'],
            'base_due' => ['nullable', 'numeric', 'min:0'],
            // Chia tiền cho nhiều hóa đơn / phiếu nhập: [{order_id, amount, discount}, ...]
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*.order_id' => ['required_with:allocations', 'integer'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0'],
            'allocations.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function receiveCustomerPayment(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate($this->paymentRules());

        DB::transaction(function () use ($customer, $data): void {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $amount = round((float) $data['amount'], 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round($amount + $discount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages(['amount' => 'Số tiền thanh toán phải lớn hơn 0.']);
            }
            if ($total > (float) $locked->debt_balance + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Số tiền thu và giảm trừ không được vượt quá công nợ hiện tại.']);
            }

            $meta = $this->paymentMeta($data, $amount, $discount, (float) $locked->debt_balance);
            $allocatedAmount = 0.0;
            $allocatedDiscount = 0.0;

            foreach ($this->normalizeAllocations($data, $amount, $discount) as $orderId => $row) {
                $sale = Sale::query()->lockForUpdate()->where('customer_id', $locked->id)->where('status', 'completed')->find($orderId);
                if (! $sale) {
                    throw ValidationException::withMessages(['amount' => 'Hóa đơn không tồn tại hoặc không thuộc khách hàng này.']);
                }
                if ($row['amount'] + $row['discount'] > max(0, (float) $sale->grand_total - (float) $sale->paid_amount) + 0.005) {
                    throw ValidationException::withMessages(['amount' => "Số tiền vượt quá khoản còn nợ của hóa đơn {$sale->code}."]);
                }
                $sale->increment('paid_amount', $row['amount'] + $row['discount']);
                $this->recordCustomerDebt($locked->id, $row['amount'], $row['discount'], $data, $sale->id, $meta);
                $allocatedAmount += $row['amount'];
                $allocatedDiscount += $row['discount'];
            }

            if ($allocatedAmount > $amount + 0.005 || $allocatedDiscount > $discount + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Tổng tiền chia cho các hóa đơn vượt quá số tiền thanh toán.']);
            }

            // Phần còn lại không gắn hóa đơn nào => trừ thẳng vào công nợ tổng
            $this->recordCustomerDebt($locked->id, round($amount - $allocatedAmount, 2), round($discount - $allocatedDiscount, 2), $data, null, $meta);
            $locked->decrement('debt_balance', $total);
        });

        return back()->with('success', 'Đã ghi nhận thu công nợ khách hàng.');
    }

    private function recordCustomerDebt(int $customerId, float $amount, float $discount, array $data, ?int $saleId, array $meta = []): void
    {
        $base = [
            'customer_id' => $customerId,
            'type' => 'decrease',
            'source_type' => $saleId ? Sale::class : null,
            'source_id' => $saleId,
            'user_id' => auth()->id(),
        ] + $meta;

        if ($amount > 0) {
            CustomerDebt::query()->create($base + ['amount' => $amount, 'payment_method' => $data['payment_method'], 'note' => $data['note'] ?? null]);
        }
        if ($discount > 0) {
            CustomerDebt::query()->create($base + ['amount' => $discount, 'payment_method' => 'discount', 'note' => trim('Giảm trừ công nợ. '.($data['note'] ?? ''))]);
        }
    }

    public function paySupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate($this->paymentRules());

        DB::transaction(function () use ($supplier, $data): void {
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $amount = round((float) $data['amount'], 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            $total = round($amount + $discount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages(['amount' => 'Số tiền thanh toán phải lớn hơn 0.']);
            }
            if ($total > (float) $locked->debt_balance + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Số tiền chi và giảm trừ không được vượt quá công nợ hiện tại.']);
            }

            $meta = $this->paymentMeta($data, $amount, $discount, (float) $locked->debt_balance);
            $allocatedAmount = 0.0;
            $allocatedDiscount = 0.0;

            foreach ($this->normalizeAllocations($data, $amount, $discount) as $orderId => $row) {
                $import = StockImport::query()->lockForUpdate()->where('supplier_id', $locked->id)->find($orderId);
                if (! $import) {
                    throw ValidationException::withMessages(['amount' => 'Phiếu nhập không tồn tại hoặc không thuộc nhà cung cấp này.']);
                }
                $due = max(0, (float) $import->grand_total - (float) $import->paid_amount);
                if ($row['amount'] + $row['discount'] > $due + 0.005) {
                    throw ValidationException::withMessages(['amount' => "Số tiền vượt quá khoản còn nợ của phiếu {$import->code}."]);
                }
                $import->increment('paid_amount', $row['amount'] + $row['discount']);
                $this->recordSupplierDebt($locked->id, $row['amount'], $row['discount'], $data, $import->id, $meta);
                $allocatedAmount += $row['amount'];
                $allocatedDiscount += $row['discount'];
            }

            if ($allocatedAmount > $amount + 0.005 || $allocatedDiscount > $discount + 0.005) {
                throw ValidationException::withMessages(['amount' => 'Tổng tiền chia cho các phiếu vượt quá số tiền thanh toán.']);
            }

            // Phần còn lại không gắn phiếu nào => trừ thẳng vào công nợ tổng
            $this->recordSupplierDebt($locked->id, round($amount - $allocatedAmount, 2), round($discount - $allocatedDiscount, 2), $data, null, $meta);
            $locked->decrement('debt_balance', $total);
        });

        return back()->with('success', 'Đã ghi nhận thanh toán công nợ nhà cung cấp.');
    }

    private function recordSupplierDebt(int $supplierId, float $amount, float $discount, array $data, ?int $importId, array $meta = []): void
    {
        $base = [
            'supplier_id' => $supplierId,
            'type' => 'decrease',
            'source_type' => $importId ? StockImport::class : null,
            'source_id' => $importId,
            'user_id' => auth()->id(),
        ] + $meta;

        if ($amount > 0) {
            SupplierDebt::query()->create($base + ['amount' => $amount, 'payment_method' => $data['payment_method'], 'note' => $data['note'] ?? null]);
        }
        if ($discount > 0) {
            SupplierDebt::query()->create($base + ['amount' => $discount, 'payment_method' => 'discount', 'note' => trim('Giảm trừ công nợ. '.($data['note'] ?? ''))]);
        }
    }
}
