<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\StockImport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TaxDeclarationController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $year = (int) ($filters['year'] ?? now()->year);
        $period = (int) ($filters['period'] ?? (int) ceil(now()->month / 3));
        $from = Carbon::create($year, (($period - 1) * 3) + 1, 1)->startOfDay();
        $to = $from->copy()->addMonths(3)->subSecond();

        $sales = Sale::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['id', 'code', 'created_at', 'subtotal', 'discount', 'tax', 'grand_total']);

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

        return Inertia::render('Taxes/Index', [
            'filters' => ['year' => $year, 'period' => $period],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'output_vat' => (float) $sales->sum('tax'),
                'sales_count' => $sales->count(),
                'vat_marked_purchase_value' => (float) $imports->sum('vat_marked_purchase_value'),
                'purchase_count' => $imports->count(),
                'tax_expenses' => (float) $taxExpenses->sum('amount'),
            ],
            'sales' => $sales,
            'imports' => $imports,
            'taxExpenses' => $taxExpenses,
        ]);
    }
}
