<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\CategoryAttribute;
use App\Models\CategoryAttributeValue;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('status') && $request->status !== '', fn ($q) =>
                $q->where('is_active', $request->status)
            )

            ->when($request->filled('sort_by'), function ($q) use ($request) {
                $order = $request->get('sort_order', 'asc');

                $q->orderBy($request->sort_by, $order);
            }, function ($q) {
                $q->latest();
            })

            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(
                'search',
                'status',
                'sort_by',
                'sort_order'
            ),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        DB::transaction(function () use ($data, &$category) {

            $category = Category::create($data);

            if (!empty($data['attributes'])) {

                foreach ($data['attributes'] as $attr) {

                    $attribute = CategoryAttribute::create([
                        'category_id' => $category->id,
                        'name' => $attr['name'],
                    ]);

                    if (!empty($attr['options'])) {

                        foreach ($attr['options'] as $value) {

                            CategoryAttributeValue::create([
                                'attribute_id' => $attribute->id,
                                'value' => $value,
                            ]);
                        }
                    }
                }
            }
        });

        return back()->with('success', 'Đã thêm danh mục');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        DB::transaction(function () use ($category, $data) {

            $category->update($data);

            //  xóa attribute cũ
            $category->attributes()->delete();

            //  tạo lại
            if (!empty($data['attributes'])) {

                foreach ($data['attributes'] as $attr) {

                    $attribute = CategoryAttribute::create([
                        'category_id' => $category->id,
                        'name' => $attr['name'],
                    ]);

                    if (!empty($attr['options'])) {

                        foreach ($attr['options'] as $value) {

                            CategoryAttributeValue::create([
                                'attribute_id' => $attribute->id,
                                'value' => $value,
                            ]);
                        }
                    }
                }
            }
        });

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

    public function create()
    {
        return Inertia::render('Categories/Form', [
            'category' => null
        ]);
    }

    public function edit(Category $category)
    {
        $category->load('attributes.values');

        return Inertia::render('Categories/Form', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,

                'attributes' => $category->attributes->map(function ($attr) {
                    return [
                        'name' => $attr->name,
                        'options' => $attr->values->pluck('value')
                    ];
                })
            ]
        ]);
    }


}