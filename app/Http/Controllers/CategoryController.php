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
        $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page', 10) : 10;
        $categories = Category::query()
            ->with('categoryAttributes.values')
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('status') && $request->status !== '', fn ($q) =>
                $q->where('is_active', $request->status)
            )

            ->when(in_array($request->query('sort_by'), ['id', 'name', 'is_active'], true), function ($q) use ($request) {
                $order = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';

                $q->orderBy($request->sort_by, $order);
            }, function ($q) {
                $q->latest();
            })

            ->paginate($perPage)
            ->through(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'is_active' => $cat->is_active,

                    'attributes' => ($cat->categoryAttributes ?? collect())
                        ->map(function ($attr) {

                            return [
                                'id' => $attr->id,
                                'name' => $attr->name,

                                // CHUẨN HÓA FORMAT
                                'values' => ($attr->values ?? collect())
                                    ->map(fn ($v) => [
                                        'value' => $v->value
                                    ])
                                    ->values()
                                    ->toArray(),
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];
            })
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => [...$request->only(
                'search',
                'status',
                'sort_by',
                'sort_order',
                'per_page'
            ), 'per_page' => $perPage],
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

        if ($request->expectsJson()) {
            return response()->json(['data' => $category], 201);
        }

        return back()->with('success', 'Đã thêm danh mục');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        // Lúc lưu, giao diện thay thế toàn bộ danh sách thuộc tính. Không cho
        // bỏ nhóm/giá trị đã được dùng để tạo biến thể, tránh làm người dùng
        // hiểu rằng dữ liệu biến thể lịch sử cũng đã được thay đổi.
        $currentAttributes = $category->categoryAttributes()->with('values')->get();
        $nextAttributes = collect($data['attributes'] ?? [])
            ->mapWithKeys(fn (array $attribute) => [
                mb_strtolower(trim((string) ($attribute['name'] ?? ''))) => collect($attribute['options'] ?? [])
                    ->map(fn ($value) => mb_strtolower(trim((string) $value)))
                    ->filter()
                    ->values(),
            ]);
        $removesExistingOption = $currentAttributes->contains(function (CategoryAttribute $attribute) use ($nextAttributes): bool {
            $name = mb_strtolower(trim($attribute->name));
            if (! $nextAttributes->has($name)) return true;

            $nextValues = $nextAttributes->get($name);
            return $attribute->values->contains(fn (CategoryAttributeValue $value) => ! $nextValues->contains(mb_strtolower(trim($value->value))));
        });
        $hasVariants = \App\Models\ProductVariant::query()
            ->whereIn('product_id', $category->products()->select('id'))
            ->exists();

        if ($removesExistingOption && $hasVariants) {
            return back()->withErrors([
                'attributes' => 'Không thể xóa nhóm thuộc tính hoặc giá trị của danh mục vì đã có sản phẩm biến thể sử dụng. Hãy vào từng Sản phẩm, đổi hoặc xóa các biến thể liên quan trước; với biến thể đã phát sinh bán/nhập, hãy giữ dữ liệu lịch sử và chỉ thêm giá trị mới.',
            ]);
        }

        DB::transaction(function () use ($category, $data) {

            $category->update($data);

            //  xóa attribute cũ
            $category->categoryAttributes()->delete();

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

        $productCount = $category->products()->withTrashed()->count();
        if ($productCount > 0) {
            return back()->withErrors(['error' => "Không thể xóa vĩnh viễn danh mục vì còn {$productCount} sản phẩm (kể cả sản phẩm trong thùng rác). Hãy chuyển các sản phẩm sang danh mục khác hoặc xóa vĩnh viễn từng sản phẩm chưa có phát sinh trước. Sản phẩm đã có hóa đơn/phiếu nhập phải được giữ trong thùng rác."]);
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


}
