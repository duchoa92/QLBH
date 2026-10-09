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
        $isCustomer = $entity === 'customer';
        $model = $isCustomer ? Customer::query() : Supplier::query();
        $nameColumn = $isCustomer ? 'full_name' : 'name';

        $rows = $model->where('debt_balance', '>', 0)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where($nameColumn, 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderByDesc('debt_balance')
            ->paginate(15)->withQueryString();

        $partyIds = collect($rows->items())->pluck('id');
        $orders = $isCustomer
            ? Sale::query()->with('customer:id,full_name,code,phone')->whereIn('customer_id', $partyIds)->where('status', 'completed')
                ->whereRaw('grand_total > paid_amount')->orderBy('created_at')->get()
                ->map(fn (Sale $sale) => [
                    'id' => $sale->id, 'code' => $sale->code, 'party_id' => $sale->customer_id,
                    'party_name' => $sale->customer?->full_name, 'date' => $sale->created_at?->format('d/m/Y H:i'),
                    'total' => (float) $sale->grand_total, 'paid' => (float) $sale->paid_amount,
                    'due' => max(0, (float) $sale->grand_total - (float) $sale->paid_amount), 'href' => route('sales.show', $sale),
                ])->values()
            : StockImport::query()->with('supplier:id,name,code,phone')->whereIn('supplier_id', $partyIds)
                ->whereRaw('grand_total > paid_amount')->orderBy('import_date')->get()
                ->map(fn (StockImport $import) => [
                    'id' => $import->id, 'code' => $import->code, 'party_id' => $import->supplier_id,
                    'party_name' => $import->supplier?->name, 'date' => $import->import_date?->format('d/m/Y H:i'),
                    'total' => (float) $import->grand_total, 'paid' => (float) $import->paid_amount,
                    'due' => max(0, (float) $import->grand_total - (float) $import->paid_amount), 'href' => route('stock.show', $import),
                ])->values();

        $history = $isCustomer
            ? CustomerDebt::query()->with('customer:id,full_name,code')->where('type', 'decrease')->whereNotNull('payment_method')->latest()->limit(100)->get()
                ->map(fn (CustomerDebt $payment) => ['id' => $payment->id, 'party' => $payment->customer?->full_name, 'code' => $payment->source?->code, 'href' => $payment->source instanceof Sale ? route('sales.show', $payment->source) : null, 'amount' => (float) $payment->amount, 'method' => $payment->payment_method, 'note' => $payment->note, 'date' => $payment->created_at?->format('d/m/Y H:i')])
            : SupplierDebt::query()->with('supplier:id,name,code')->where('type', 'decrease')->latest()->limit(100)->get()
                ->map(fn (SupplierDebt $payment) => ['id' => $payment->id, 'party' => $payment->supplier?->name, 'code' => $payment->source?->code, 'href' => $payment->source instanceof StockImport ? route('stock.show', $payment->source) : null, 'amount' => (float) $payment->amount, 'method' => $payment->payment_method, 'note' => $payment->note, 'date' => $payment->created_at?->format('d/m/Y H:i')]);

        return Inertia::render('Debts/Index', [
            'entity' => $entity,
            'rows' => $rows,
            'filters' => ['search' => $search],
            'summary' => [
                'total' => (float) ($isCustomer ? Customer::sum('debt_balance') : Supplier::sum('debt_balance')),
                'count' => (int) ($isCustomer ? Customer::where('debt_balance', '>', 0)->count() : Supplier::where('debt_balance', '>', 0)->count()),
            ],
            'orders' => $orders,
            'history' => $history,
        ]);
    }

    public function receiveCustomerPayment(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'note' => ['nullable', 'string', 'max:1000'],
            'order_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($customer, $data): void {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $amount = round((float) $data['amount'], 2);
            if ($amount > (float) $locked->debt_balance) {
                throw ValidationException::withMessages(['amount' => 'Số tiền thu không được vượt quá công nợ hiện tại.']);
            }
            $sale = null;
            if (!empty($data['order_id'])) {
                $sale = Sale::query()->lockForUpdate()->where('customer_id', $locked->id)->where('status', 'completed')->findOrFail($data['order_id']);
                if ($amount > max(0, (float) $sale->grand_total - (float) $sale->paid_amount)) {
                    throw ValidationException::withMessages(['amount' => 'Số tiền thu vượt quá khoản còn nợ của hóa đơn.']);
                }
                $sale->increment('paid_amount', $amount);
            }
            CustomerDebt::query()->create([
                'customer_id' => $locked->id,
                'type' => 'decrease',
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'source_type' => $sale ? Sale::class : null,
                'source_id' => $sale?->id,
                'note' => $data['note'] ?? null,
            ]);
            $locked->decrement('debt_balance', $amount);
        });

        return back()->with('success', 'Đã ghi nhận thu công nợ khách hàng.');
    }

    public function paySupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,bank,card'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($supplier, $data): void {
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $amount = round((float) $data['amount'], 2);
            if ($amount > (float) $locked->debt_balance) {
                throw ValidationException::withMessages(['amount' => 'Số tiền chi không được vượt quá công nợ hiện tại.']);
            }
            $import = null;
            if (!empty($data['order_id'])) {
                $import = StockImport::query()->lockForUpdate()->where('supplier_id', $locked->id)->findOrFail($data['order_id']);
                if ($amount > max(0, (float) $import->grand_total - (float) $import->paid_amount)) {
                    throw ValidationException::withMessages(['amount' => 'Số tiền chi vượt quá khoản còn nợ của phiếu nhập.']);
                }
                $import->increment('paid_amount', $amount);
            }
            SupplierDebt::query()->create([
                'supplier_id' => $locked->id,
                'type' => 'decrease',
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'source_type' => $import ? StockImport::class : null,
                'source_id' => $import?->id,
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);
            $locked->decrement('debt_balance', $amount);
        });

        return back()->with('success', 'Đã ghi nhận thanh toán công nợ nhà cung cấp.');
    }
}
