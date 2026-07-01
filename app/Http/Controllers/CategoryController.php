<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
       return Inertia::render('Categories/Index', [
            'categories' => Category::with('parent:id,name')
                ->withTrashed()
                ->paginate(10),

            'filters' => request()->only('search')
        ]);
    }

    public function store(Request $request)
    {
        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id
        ]);

        return back()->with('success', 'Đã thêm danh mục');
    }

    public function update(Request $request, Category $category)
    {
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id
        ]);

        return back()->with('success', 'Đã cập nhật');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'Đã xóa');
    }
}