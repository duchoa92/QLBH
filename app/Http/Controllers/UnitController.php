<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function store(Request $request)
    {
        Unit::create($this->normalizedData($request));

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
        if ($unit->products()->exists()) {
            return back()->withErrors([
                'unit' => 'Đơn vị đang được dùng cho sản phẩm, không thể xóa.',
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
