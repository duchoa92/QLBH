<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

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
        Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'category_id' => $request->category_id
        ]);

        return back()->with('success', 'Đã thêm brand');
    }

    public function update(Request $request, Brand $brand)
    {
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
}