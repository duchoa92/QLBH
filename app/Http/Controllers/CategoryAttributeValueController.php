<?php

namespace App\Http\Controllers;

use App\Models\CategoryAttribute;
use App\Models\CategoryAttributeValue;
use Illuminate\Http\Request;

class CategoryAttributeValueController extends Controller
{
    // 🔥 Lấy danh sách value theo attribute
    public function index($attributeId)
    {
        $values = CategoryAttributeValue::where('attribute_id', $attributeId)->get();

        return response()->json($values);
    }

    // 🔥 Thêm value
    public function store(Request $request)
    {
        $request->validate([
            'attribute_id' => 'required|exists:category_attributes,id',
            'value' => 'required|string|max:255',
        ]);

        $value = CategoryAttributeValue::create([
            'attribute_id' => $request->attribute_id,
            'value' => $request->value,
        ]);

        return response()->json($value);
    }

    // 🔥 Sửa value
    public function update(Request $request, $id)
    {
        $request->validate([
            'value' => 'required|string|max:255',
        ]);

        $value = CategoryAttributeValue::findOrFail($id);

        $value->update([
            'value' => $request->value,
        ]);

        return response()->json($value);
    }

    // 🔥 Xóa value
    public function destroy($id)
    {
        $value = CategoryAttributeValue::findOrFail($id);
        $attribute = $value->attribute;
        $hasVariants = $attribute && \App\Models\ProductVariant::query()
            ->whereIn('product_id', $attribute->category->products()->select('id'))
            ->exists();
        if ($hasVariants) {
            return response()->json([
                'message' => "Không thể xóa giá trị “{$value->value}” vì danh mục đã có sản phẩm biến thể. Hãy xử lý biến thể tại Sản phẩm trước; biến thể có lịch sử bán/nhập cần được giữ lại.",
            ], 422);
        }
        $value->delete();

        return response()->json([
            'message' => 'Đã xóa'
        ]);
    }
}
