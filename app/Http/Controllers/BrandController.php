<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::with('category')
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->when($request->category_id, function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Category::select('id', 'name')->get();
    
        return Inertia::render('Brands/Index', [
            'brands' => $brands,
            'filters' => $request->only('search', 'category_id'),
            'categories' => $categories
        ]);

    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('brands', 'name')
            ],
            'category_id' => 'required'
        ], [
            'name.required' => 'Vui lòng nhập tên thương hiệu',
            'name.unique' => 'Tên thương hiệu đã tồn tại',
            'category_id.required' => 'Vui lòng chọn danh mục'
        ]);

        Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'category_id' => $request->category_id
        ]);

        return back()->with('success', 'Đã thêm brand');
    }

    public function update(Request $request, Brand $brand)
    {
         $request->validate([
            'name' => [
                'required',
                Rule::unique('brands', 'name')->ignore($brand->id)
            ],
            'category_id' => 'nullable|exists:categories,id'
        ]);
        $brand->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'category_id' => $request->category_id
        ]);

        return back()->with('success', 'Đã cập nhật');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return back()->with('success', 'Đã xóa');
    }

    public function trash()
    {
        $brands = Brand::onlyTrashed()
            ->with('category')
            ->latest()
            ->get();

        return response()->json($brands);
    }

    public function restore($id)
    {
        Brand::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Đã khôi phục');
    }

    public function forceDelete($id)
    {
        $brand = Brand::onlyTrashed()->find($id);

        if (!$brand) {
            return back()->withErrors([
                'error' => 'Thương hiệu không tồn tại hoặc chưa bị xóa'
            ]);
        }

        // check quan hệ
        if ($brand->products()->exists()) {
            return back()->withErrors([
                'error' => 'Không thể xóa vì còn dữ liệu liên quan'
            ]);
        }

        $brand->forceDelete();

        return back()->with('success', 'Đã xóa vĩnh viễn');
    }
}