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
        
       $brands = Brand::with('category')
        ->when($request->filled('search'), fn ($q) =>
            $q->where('name', 'like', '%' . $request->search . '%')
        )
        ->when($request->category_id !== null && $request->category_id !== '', fn ($q) =>
            $q->where('category_id', $request->category_id)
        )
        ->when($request->filled('sort_by'), function ($q) use ($request) {

            $order = $request->get('sort_order', 'asc');

            if ($request->sort_by === 'category_name') {
                $q->leftJoin('categories', 'brands.category_id', '=', 'categories.id')
                ->orderBy('categories.name', $order)
                ->select('brands.*');
            } else {
                $q->orderBy($request->sort_by, $order);
            }

        }, function ($q) {
            $q->latest();
        })
        ->paginate(10)
        ->withQueryString();


        $categories = Category::select('id', 'name')->get();

        return Inertia::render('Brands/Index', [
            'brands'     => $brands,
            'filters' => $request->only(
                'search',
                'category_id',
                'sort_by',
                'sort_order'
            ),
            'categories' => $categories,
        ]);
    }

    public function store(StoreBrandRequest $request)
    {
        $this->brandService->create($request->validated());
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
        if ($brand->products()->exists()) {
            return back()->withErrors(['error' => 'Không thể xóa vì còn sản phẩm thuộc thương hiệu này']);
        }

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