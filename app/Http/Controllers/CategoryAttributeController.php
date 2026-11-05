<?php

namespace App\Http\Controllers;

use App\Models\CategoryAttribute;
use Illuminate\Http\Request;

class CategoryAttributeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string'
        ]);

        return CategoryAttribute::create($request->only('category_id', 'name'));
    }

    public function update(Request $request, CategoryAttribute $attribute)
    {
        $request->validate([
            'name' => 'required|string'
        ]);

        $attribute->update([
            'name' => $request->name
        ]);

        return back();
    }

    public function destroy(CategoryAttribute $attribute)
    {
        $hasVariants = \App\Models\ProductVariant::query()
            ->whereIn('product_id', $attribute->category->products()->select('id'))
            ->exists();
        if ($hasVariants) {
            return back()->withErrors([
                'attribute' => "Không thể xóa thuộc tính “{$attribute->name}” vì danh mục đã có sản phẩm biến thể. Hãy xử lý biến thể tại Sản phẩm trước; biến thể có lịch sử bán/nhập cần được giữ lại.",
            ]);
        }

        $attribute->delete();

        return back();
    }
}
