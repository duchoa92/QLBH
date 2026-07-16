<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::with('parent:id,name')
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters'    => $request->only('search'),
        ]);
    }

    public function create()
    {
        $categories = Category::select('id', 'name')->get();
        return Inertia::render('Categories/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        Category::create($data);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Đã thêm danh mục');
    }

    public function edit(Category $category)
    {
        $categories = Category::where('id', '!=', $category->id)
            ->select('id', 'name')
            ->get();

        return Inertia::render('Categories/Edit', [
            'category'   => $category,
            'categories' => $categories,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        $category->update($data);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Đã cập nhật danh mục');
    }

    public function destroy(Category $category)
    {
       
        $category->delete(); // ✅ đúng chuẩn

        return back()->with('success', 'Đã chuyển vào thùng rác');
    }

    public function trash()
    {
        $categories = Category::onlyTrashed()
            ->with('parent:id,name')
            ->latest()
            ->get();

        return response()->json($categories);
    }

    public function restore($id)
    {
        Category::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Đã khôi phục danh mục thành công');
    }

    public function forceDelete($id)
    {
        $category = Category::withTrashed()->find($id);

        if (! $category) {
            return back()->withErrors(['error' => 'Danh mục không tồn tại']);
        }

        if ($category->products()->exists()) {
            return back()->withErrors(['error' => 'Không thể xóa vì còn sản phẩm thuộc Danh mục này']);
        }

        $category->forceDelete();
        return back()->with('success', 'Đã xóa vĩnh viễn danh mục');
    }

    public function toggleStatus(Category $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái hoạt động');
    }
}
