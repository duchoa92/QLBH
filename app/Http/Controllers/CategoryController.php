<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('status'), fn ($q) =>
                $q->where('is_active', $request->status)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        Category::create($data);

        return back()->with('success', 'Đã thêm danh mục');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        $category->update($data);

        return back()->with('success', 'Đã cập nhật danh mục');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Đã chuyển vào thùng rác');
    }

    public function trash()
    {
        $categories = Category::onlyTrashed()->latest()->get();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'total' => $categories->count()
            ]
        ]);
    }

    public function restore($id)
    {
        Category::withTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Đã khôi phục danh mục');
    }

    public function forceDelete($id)
    {
        $category = Category::withTrashed()->find($id);

        if (! $category) {
            return back()->withErrors(['error' => 'Danh mục không tồn tại']);
        }

        if ($category->products()->exists()) {
            return back()->withErrors(['error' => 'Không thể xóa vì còn sản phẩm']);
        }

        $category->forceDelete();

        return back()->with('success', 'Đã xóa vĩnh viễn');
    }

    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', 'Đã cập nhật trạng thái');
    }
}