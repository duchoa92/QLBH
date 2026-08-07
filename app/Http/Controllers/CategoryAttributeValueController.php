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
        $value->delete();

        return response()->json([
            'message' => 'Đã xóa'
        ]);
    }
}