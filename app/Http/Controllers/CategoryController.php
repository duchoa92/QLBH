<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only('search')
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', Rule::unique('categories')]
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'is_active' => true
        ]);

        return back()->with('success', 'Đã thêm danh mục');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => [
                'required',
                Rule::unique('categories')->ignore($category->id)
            ]
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name)
        ]);

        return back()->with('success', 'Đã cập nhật');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'Đã chuyển vào thùng rác');
    }

    /* ================= TRASH ================= */

    public function trash()
    {
        return response()->json(
            Category::onlyTrashed()->latest()->get()
        );
    }

    public function restore($id)
    {
        Category::withTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Khôi phục thành công');
    }

    public function forceDelete($id)
    {
        $item = Category::withTrashed()->findOrFail($id);

        // optional: check ràng buộc
        if ($item->products()->exists()) {
            return back()->withErrors([
                'error' => 'Không thể xóa vì còn sản phẩm'
            ]);
        }

        $item->forceDelete();

        return back()->with('success', 'Đã xóa vĩnh viễn');
    }

    public function toggleStatus($id)
    {
        $item = Category::findOrFail($id);

        $item->is_active = !$item->is_active;
        $item->save();

        return back();
    }
}