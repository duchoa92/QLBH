<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\Brand\BrandService;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandController extends Controller
{
    // Giữ nguyên constructor tiêm Service
    public function __construct(protected BrandService $brandService)
    {
    }

    public function index(Request $request)
    {
       $perPage = in_array((int) $request->query('per_page', 10), [10, 20, 50, 100], true)
           ? (int) $request->query('per_page', 10) : 10;

       $brands = Brand::with('categories:id,name')
        ->when($request->filled('search'), fn ($q) =>
            $q->where('name', 'like', '%' . $request->search . '%')
        )
        // Thương hiệu dùng chung: lọc theo danh mục = thương hiệu có liên kết với danh mục đó
        ->when($request->category_id !== null && $request->category_id !== '', fn ($q) =>
            $q->whereHas('categories', fn ($c) => $c->where('categories.id', $request->category_id))
        )
        ->when(in_array($request->query('sort_by'), ['id', 'name', 'is_active'], true), function ($q) use ($request) {

            $order = $request->query('sort_order') === 'desc' ? 'desc' : 'asc';

            $q->orderBy($request->sort_by, $order);

        }, function ($q) {
            $q->latest();
        })
        ->paginate($perPage)
        ->through(function (Brand $brand) {
            // Danh mục sắp theo tên, bỏ dữ liệu bảng trung gian khỏi JSON.
            $brand->setRelation(
                'categories',
                $brand->categories->sortBy('name')->values()->each->makeHidden('pivot')
            );

            return $brand;
        })
        ->withQueryString();


        $categories = Category::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Brands/Index', [
            'brands'     => $brands,
            'filters' => [...$request->only(
                'search',
                'category_id',
                'sort_by',
                'sort_order',
                'per_page'
            ), 'per_page' => $perPage],
            'categories' => $categories,
        ]);
    }

    public function store(StoreBrandRequest $request)
    {
        $brand = $this->brandService->create($request->validated());
        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'is_active' => (bool) $brand->is_active,
                'category_ids' => $brand->categories()->pluck('categories.id')->values()->all(),
            ]], 201);
        }
        return back()->with('success', 'Đã thêm thương hiệu thành công');
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $this->brandService->update($brand, $request->validated());
        return back()->with('success', 'Đã cập nhật thương hiệu thành công');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete(); // Hoặc $this->brandService->delete($brand);
        return back()->with('success', 'Đã chuyển thương hiệu vào thùng rác');
    }

    // Tối ưu hàm này để không kéo toàn bộ data làm nặng hệ thống
    public function trash()
    {
        // Chỉ lấy những thông tin tối thiểu để đếm hoặc hiển thị nhanh modal
        $brands = Brand::onlyTrashed()->select('id', 'name', 'deleted_at')->latest()->get();

        return response()->json([
            'data' => $brands,
            'meta' => [
                'total' => $brands->count()
            ]
        ]);
    }

    public function restore($id)
    {
        Brand::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Đã khôi phục thương hiệu thành công');
    }

    public function forceDelete($id)
    {
        $brand = Brand::withTrashed()->find($id);

        if (!$brand) {
            return back()->withErrors(['error' => 'Thương hiệu không tồn tại']);
        }

        // Ngăn chặn xóa cứng nếu dính khóa ngoại tới sản phẩm
        $productCount = $brand->products()->withTrashed()->count();
        if ($productCount > 0) {
            return back()->withErrors(['error' => "Không thể xóa vĩnh viễn thương hiệu vì còn {$productCount} sản phẩm thuộc thương hiệu này. Hãy đổi thương hiệu tại từng sản phẩm hoặc xóa vĩnh viễn các sản phẩm chưa có phát sinh trước. Sản phẩm đã có lịch sử bán/nhập phải được giữ lại."]);
        }

        $brand->categories()->detach();
        $brand->forceDelete();
        return back()->with('success', 'Đã xóa vĩnh viễn thương hiệu');
    }

    public function toggleStatus($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->update(['is_active' => !$brand->is_active]);
        
        // Trả về dữ liệu cập nhật, Inertia sẽ tự động đồng bộ hóa client-side state
        return back()->with('success', 'Đã cập nhật trạng thái hoạt động');
    }
}
