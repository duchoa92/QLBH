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
        $attribute->delete();

        return back();
    }
}