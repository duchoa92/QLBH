<?php

namespace App\Http\Controllers;

use App\Exports\TaxDeclarationExport;
use App\Exports\TaxDeclarationSheet;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\StockImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaxDeclarationController extends Controller
{
    private const NO_BANK_ACCOUNT = '__none__';

    private const METHOD_LABELS = ['cash' => 'Tiền mặt', 'bank' => 'Chuyển khoản', 'mixed' => 'Kết hợp', 'card' => 'Thẻ'];

    public function index(Request $request): Response|BinaryFileResponse
    {
        $report = $this->buildReport($request);

        // Xuất Excel: dùng chính trang này với ?export=xlsx để không phải thêm route mới.
        if ($request->query('export') === 'xlsx') {
            return $this->exportExcel($report);
        }

        return Inertia::render('Taxes/Index', [
            'filters' => $report['filters'],
            'range' => $report['range'],
            'summary' => $report['summary'],
            'bankOptions' => $report['bankOptions'],
            'bankBreakdown' => $report['bankBreakdown'],
            'sales' => $report['sales'],
            'items' => $report['items'],
            'imports' => $report['imports'],
            'taxExpenses' => $report['taxExpenses'],
        ]);
    }

    private function buildReport(Request $request): array
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'integer', 'between:1,4'],
            'search' => ['nullable', 'string', 'max:100'],
            'vat' => ['nullable', 'in:with_vat,without_vat'],
            'receipt' => ['nullable', 'in:cash,bank,mixed,card,debt'],
            'bank_account' => ['nullable', 'string', 'max:50'],
        ]);

        $year = (int) ($filters['year'] ?? now()->year);
        $period = (int) ($filters['period'] ?? (int) ceil(now()->month / 3));
        $search = trim((string) ($filters['search'] ?? ''));
        $vat = (string) ($filters['vat'] ?? '');
        $receipt = (string) ($filters['receipt'] ?? '');
        $bankAccount = (string) ($filters['bank_account'] ?? '');
        $from = Carbon::create($year, (($period - 1) * 3) + 1, 1)->startOfDay();
        $to = $from->copy()->addMonths(3)->subSecond();

        $periodSales = fn (): Builder => Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to]);

        $bankLabels = $this->bankLabels();

        $saleModels = $periodSales()
            ->with(['customer:id,full_name', 'items.product:id,name,sku', 'items.productImei:id,imei'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('code', 'like', $like)
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('full_name', 'like', $like)
                            ->orWhere('phone', 'like', $like))
                        ->orWhereHas('items.product', fn (Builder $product) => $product
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like))
                        ->orWhereHas('items.productImei', fn (Builder $imei) => $imei->where('imei', 'like', $like));
                });
            })
            ->when($vat === 'with_vat', fn (Builder $query) => $query->where('tax', '>', 0))
            ->when($vat === 'without_vat', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('tax')->orWhere('tax', '<=', 0)
            ))
            ->when($receipt === 'cash', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('payment_method', 'cash')
                    ->orWhereRaw('COALESCE(cash_received_amount, 0) - COALESCE(change_amount, 0) > 0')
            ))
            ->when($receipt === 'bank', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('payment_method', 'bank')
                    ->orWhere('bank_received_amount', '>', 0)
            ))
            ->when($receipt === 'mixed', fn (Builder $query) => $query->where('payment_method', 'mixed'))
            ->when($receipt === 'card', fn (Builder $query) => $query->where('payment_method', 'card'))
            ->when($receipt === 'debt', fn (Builder $query) => $query->whereColumn('paid_amount', '<', 'grand_total'))
            ->when($bankAccount === self::NO_BANK_ACCOUNT, fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('bank_account')->orWhere('bank_account', '')
            ))
            ->when($bankAccount !== '' && $bankAccount !== self::NO_BANK_ACCOUNT, fn (Builder $query) => $query->where('bank_account', $bankAccount))
            ->orderBy('created_at')
            ->get([
                'id', 'code', 'customer_id', 'created_at', 'subtotal', 'discount', 'tax', 'grand_total',
                'paid_amount', 'cash_received_amount', 'bank_received_amount', 'change_amount',
                'payment_method', 'bank_account',
            ]);

        $sales = collect();
        $items = collect();

        foreach ($saleModels as $sale) {
            $grand = (float) $sale->grand_total;
            $paid = (float) $sale->paid_amount;
            $method = (string) $sale->payment_method;
            $tax = (float) $sale->tax;

            // Tiền mặt thực nhận = tiền khách đưa trừ tiền thối (giống cách tính ở Dashboard).
            $cash = max(0, (float) $sale->cash_received_amount - (float) $sale->change_amount);
            $bank = (float) $sale->bank_received_amount + ($method === 'card' ? $paid : 0);

            // Hóa đơn cũ chưa lưu số tiền theo từng kênh thì suy ra từ hình thức thanh toán.
            if ($method === 'cash' && $cash <= 0) {
                $cash = min($paid, $grand);
            }
            if ($method === 'bank' && $bank <= 0) {
                $bank = min($paid, $grand);
            }

            $account = trim((string) $sale->bank_account);
            $accountLabel = $account !== '' ? ($bankLabels[$account] ?? $account) : null;

            $sales->push([
                'id' => $sale->id,
                'code' => $sale->code,
                'customer_name' => $sale->customer?->full_name,
                'created_at' => $sale->created_at,
                'subtotal' => (float) $sale->subtotal,
                'discount' => (float) $sale->discount,
                'tax' => $tax,
                'grand_total' => $grand,
                'payment_method' => $method,
                'bank_account' => $account !== '' ? $account : null,
                'bank_account_label' => $accountLabel,
                'cash_amount' => $cash,
                'bank_amount' => $bank,
                'debt_amount' => max(0, $grand - $paid),
            ]);

            // VAT của hóa đơn được chia cho các dòng chịu VAT theo tỷ lệ thành tiền (số ước tính, hệ thống chỉ lưu VAT theo hóa đơn).
            $vatBase = (float) $sale->items->where('vat_applied', true)->sum(fn ($item) => (float) $item->subtotal);

            foreach ($sale->items as $item) {
                $quantity = (int) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $net = (float) $item->subtotal;
                $vatApplied = (bool) $item->vat_applied;

                $items->push([
                    'key' => $sale->id.'-'.$item->id,
                    'sale_id' => $sale->id,
                    'sale_code' => $sale->code,
                    'created_at' => $sale->created_at,
                    'customer_name' => $sale->customer?->full_name,
                    'product_name' => $item->product?->name ?? 'Sản phẩm đã xóa',
                    'sku' => $item->product?->sku,
                    'imei' => $item->productImei?->imei,
                    'unit_name' => $item->unit_name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => max(0, $unitPrice * $quantity - $net),
                    'subtotal' => $net,
                    'vat_applied' => $vatApplied,
                    'vat_amount' => $vatApplied && $vatBase > 0 ? round($tax * $net / $vatBase) : 0,
                    'payment_method' => $method,
                    'bank_account_label' => $accountLabel,
                ]);
            }
        }

        $imports = StockImport::query()
            ->with('supplier:id,name')
            ->where('status', 'completed')
            ->whereBetween('import_date', [$from, $to])
            ->whereHas('items', fn ($query) => $query->where('has_vat', true))
            ->orderBy('import_date')
            ->get(['id', 'code', 'supplier_id', 'import_date', 'grand_total']);

        // cost_price is per unit, so compute taxable purchase value from actual item quantities.
        $imports->each(function (StockImport $import): void {
            $import->vat_marked_purchase_value = (float) $import->items()
                ->where('has_vat', true)
                ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as aggregate')
                ->value('aggregate');
        });

        $taxExpenses = Expense::query()
            ->where('category', 'tax')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('expense_date')
            ->get(['id', 'description', 'expense_date', 'amount', 'recipient']);

        // Tổng tiền nhận theo từng tài khoản ngân hàng (trên các hóa đơn đang lọc).
        $bankBreakdown = $sales
            ->filter(fn (array $sale) => $sale['bank_account'] !== null && $sale['bank_amount'] > 0)
            ->groupBy('bank_account')
            ->map(fn ($group, $account) => [
                'value' => (string) $account,
                'label' => $bankLabels[$account] ?? (string) $account,
                'count' => $group->count(),
                'amount' => (float) $group->sum('bank_amount'),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'filters' => [
                'year' => $year,
                'period' => $period,
                'search' => $search,
                'vat' => $vat,
                'receipt' => $receipt,
                'bank_account' => $bankAccount,
            ],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'output_vat' => (float) $sales->sum('tax'),
                'sales_count' => $sales->count(),
                'items_count' => $items->count(),
                'items_quantity' => (int) $items->sum('quantity'),
                'cash_received' => (float) $sales->sum('cash_amount'),
                'bank_received' => (float) $sales->sum('bank_amount'),
                'debt_total' => (float) $sales->sum('debt_amount'),
                'vat_marked_purchase_value' => (float) $imports->sum('vat_marked_purchase_value'),
                'purchase_count' => $imports->count(),
                'tax_expenses' => (float) $taxExpenses->sum('amount'),
            ],
            'bankOptions' => $this->bankOptions($periodSales, $bankLabels),
            'bankBreakdown' => $bankBreakdown,
            'sales' => $sales->values(),
            'items' => $items->values(),
            'imports' => $imports,
            'taxExpenses' => $taxExpenses,
        ];
    }

    private function exportExcel(array $report): BinaryFileResponse
    {
        $filters = $report['filters'];
        $summary = $report['summary'];
        $bankLabels = $this->bankLabels();
        $date = fn ($value) => $value ? Carbon::parse($value)->format('d/m/Y H:i') : '';
        $method = fn (?string $value) => self::METHOD_LABELS[$value] ?? (string) $value;

        $appliedFilters = collect([
            $filters['search'] !== '' ? 'Từ khóa: '.$filters['search'] : null,
            $filters['vat'] === 'with_vat' ? 'Hóa đơn có VAT' : ($filters['vat'] === 'without_vat' ? 'Hóa đơn không VAT' : null),
            match ($filters['receipt']) {
                'cash' => 'Có nhận tiền mặt',
                'bank' => 'Có nhận chuyển khoản',
                'mixed' => 'Thanh toán kết hợp',
                'card' => 'Quẹt thẻ',
                'debt' => 'Còn nợ chưa thu đủ',
                default => null,
            },
            $filters['bank_account'] === self::NO_BANK_ACCOUNT
                ? 'Không ghi tài khoản'
                : ($filters['bank_account'] !== '' ? 'Tài khoản: '.($bankLabels[$filters['bank_account']] ?? $filters['bank_account']) : null),
        ])->filter()->implode('; ');

        $summarySheet = new TaxDeclarationSheet('Tổng hợp', ['Chỉ tiêu', 'Giá trị'], [
            ['Kỳ kê khai', 'Quý '.$filters['period'].'/'.$filters['year'].' ('.Carbon::parse($report['range']['from'])->format('d/m/Y').' – '.Carbon::parse($report['range']['to'])->format('d/m/Y').')'],
            ['Bộ lọc đang áp dụng', $appliedFilters !== '' ? $appliedFilters : 'Không lọc'],
            ['Số hóa đơn bán', $summary['sales_count']],
            ['Số dòng sản phẩm bán', $summary['items_count']],
            ['Tổng số lượng bán', $summary['items_quantity']],
            ['Thuế đầu ra đã ghi nhận', $summary['output_vat']],
            ['Tiền mặt đã nhận', $summary['cash_received']],
            ['Chuyển khoản / thẻ đã nhận', $summary['bank_received']],
            ['Nợ chưa thu', $summary['debt_total']],
            ['Giá trị hàng nhập có VAT (chưa phải tiền VAT)', $summary['vat_marked_purchase_value']],
            ['Chi phí thuế, phí đã ghi', $summary['tax_expenses']],
            ['Ghi chú', 'Bảng tổng hợp tham khảo, cần đối chiếu hóa đơn chứng từ trước khi nộp tờ khai.'],
        ], ['B']);

        $salesSheet = new TaxDeclarationSheet(
            'Hóa đơn bán',
            ['Ngày', 'Mã hóa đơn', 'Khách hàng', 'Tiền hàng', 'Giảm giá', 'Thuế đầu ra', 'Tổng thanh toán', 'Hình thức', 'TK nhận tiền', 'Tiền mặt', 'Chuyển khoản / thẻ', 'Còn nợ'],
            collect($report['sales'])->map(fn (array $sale) => [
                $date($sale['created_at']), $sale['code'], $sale['customer_name'] ?? '', $sale['subtotal'], $sale['discount'],
                $sale['tax'], $sale['grand_total'], $method($sale['payment_method']), $sale['bank_account_label'] ?? '',
                $sale['cash_amount'], $sale['bank_amount'], $sale['debt_amount'],
            ])->all(),
            ['D', 'E', 'F', 'G', 'J', 'K', 'L'],
        );

        $itemsSheet = new TaxDeclarationSheet(
            'Chi tiết sản phẩm bán',
            ['Ngày', 'Mã hóa đơn', 'Khách hàng', 'Sản phẩm', 'SKU', 'IMEI/Serial', 'ĐVT', 'Số lượng', 'Đơn giá', 'Giảm giá', 'Thành tiền', 'Chịu VAT', 'VAT phân bổ (ước tính)', 'Hình thức', 'TK nhận tiền'],
            collect($report['items'])->map(fn (array $item) => [
                $date($item['created_at']), $item['sale_code'], $item['customer_name'] ?? '', $item['product_name'], $item['sku'] ?? '',
                $item['imei'] ?? '', $item['unit_name'] ?? '', $item['quantity'], $item['unit_price'], $item['discount'],
                $item['subtotal'], $item['vat_applied'] ? 'Có' : 'Không', $item['vat_amount'],
                $method($item['payment_method']), $item['bank_account_label'] ?? '',
            ])->all(),
            ['I', 'J', 'K', 'M'],
        );

        $importsSheet = new TaxDeclarationSheet(
            'Phiếu nhập có VAT',
            ['Ngày nhập', 'Mã phiếu', 'Nhà cung cấp', 'Giá trị dòng có VAT'],
            collect($report['imports'])->map(fn ($import) => [
                Carbon::parse($import->import_date)->format('d/m/Y'), $import->code, $import->supplier?->name ?? '', $import->vat_marked_purchase_value,
            ])->all(),
            ['D'],
        );

        $expensesSheet = new TaxDeclarationSheet(
            'Chi phí thuế, phí',
            ['Ngày chi', 'Nội dung', 'Nơi nhận', 'Số tiền'],
            collect($report['taxExpenses'])->map(fn ($expense) => [
                Carbon::parse($expense->expense_date)->format('d/m/Y'), $expense->description, $expense->recipient ?? '', (float) $expense->amount,
            ])->all(),
            ['D'],
        );

        return Excel::download(
            new TaxDeclarationExport([$summarySheet, $salesSheet, $itemsSheet, $importsSheet, $expensesSheet]),
            sprintf('khai-thue-quy-%d-%d.xlsx', $filters['period'], $filters['year'])
        );
    }

    /** @return array<string, string> số tài khoản => nhãn hiển thị */
    private function bankLabels(): array
    {
        $accounts = json_decode((string) setting('bank_accounts', '[]'), true) ?: [];
        $labels = [];

        foreach ($accounts as $account) {
            $number = trim((string) ($account['bank_account'] ?? ''));
            if ($number === '') {
                continue;
            }
            $bankName = trim((string) ($account['bank_name'] ?? ''));
            $labels[$number] = trim(($bankName !== '' ? $bankName.' · ' : '').$number)
                .(! empty($account['vat_preferred']) ? ' (VAT)' : '');
        }

        return $labels;
    }

    /** Tài khoản đã cấu hình + tài khoản đã xuất hiện trên hóa đơn trong kỳ (kể cả tài khoản đã xóa khỏi cài đặt). */
    private function bankOptions(callable $periodSales, array $bankLabels): array
    {
        $used = $periodSales()
            ->whereNotNull('bank_account')
            ->where('bank_account', '!=', '')
            ->distinct()
            ->pluck('bank_account')
            ->map(fn ($account) => trim((string) $account))
            ->all();

        $options = [];
        foreach ($bankLabels as $number => $label) {
            $options[] = ['value' => (string) $number, 'label' => $label];
        }
        foreach ($used as $number) {
            if (! isset($bankLabels[$number])) {
                $options[] = ['value' => $number, 'label' => $number.' (không còn trong cài đặt)'];
            }
        }

        return $options;
    }
}
