<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 10) : 10;

        $units = Unit::query()
            ->select('id', 'name', 'short_name', 'is_active')
            ->when($request->filled('search'), function ($q) use ($request) {
                $keyword = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $keyword)->orWhere('short_name', 'like', $keyword));
            })
            ->when(in_array($request->query('sort_by'), ['id', 'name', 'short_name', 'is_active'], true), function ($q) use ($request) {
                $q->orderBy($request->query('sort_by'), $request->query('sort_order') === 'desc' ? 'desc' : 'asc');
            }, function ($q) {
                $q->orderByDesc('is_active')->orderBy('name');
            })
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [...$request->only('search', 'sort_by', 'sort_order', 'per_page'), 'per_page' => $perPage],
        ]);
    }

    public function store(Request $request)
    {
        $unit = Unit::create($this->normalizedData($request));

        if ($request->expectsJson()) {
            return response()->json(['data' => $unit], 201);
        }

        return back()->with('success', 'Đã thêm đơn vị tính');
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($this->normalizedData($request, $unit));

        return back()->with('success', 'Đã cập nhật đơn vị tính');
    }

    public function toggleStatus(Unit $unit)
    {
        $unit->update([
            'is_active' => ! $unit->is_active,
        ]);

        return back()->with('success', 'Đã cập nhật trạng thái đơn vị');
    }

    public function destroy(Unit $unit)
    {
        $productCount = $unit->products()->count();
        $conversionCount = \App\Models\Product::query()->where('conversion_unit_id', $unit->id)->count();
        $importItemCount = \DB::table('stock_import_items')->where('unit_id', $unit->id)->count();
        $saleItemCount = \DB::table('sale_items')->where('unit_id', $unit->id)->count();

        if ($productCount || $conversionCount || $importItemCount || $saleItemCount) {
            $parts = array_filter([
                $productCount ? "{$productCount} sản phẩm" : null,
                $conversionCount ? "{$conversionCount} quy đổi đơn vị" : null,
                $importItemCount ? "{$importItemCount} dòng phiếu nhập" : null,
                $saleItemCount ? "{$saleItemCount} dòng hóa đơn" : null,
            ]);
            return back()->withErrors([
                'unit' => 'Không thể xóa đơn vị vì đã được sử dụng ('.implode(', ', $parts).'). Hãy đổi đơn vị/quy đổi tại Sản phẩm trước. Các đơn vị đã có trong phiếu nhập hoặc hóa đơn là lịch sử, không nên xóa; hãy chuyển đơn vị sang ngừng hoạt động.',
            ]);
        }

        $unit->delete();

        return back()->with('success', 'Đã xóa đơn vị tính');
    }

    private function validatedData(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($unit),
            ],
            'short_name' => [
                'nullable',
                'string',
                'max:30',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);
    }

    private function normalizedData(Request $request, ?Unit $unit = null): array
    {
        return $this->validatedData($request, $unit);
    }
}
