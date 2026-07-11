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
    public function __construct(protected BrandService $brandService)
    {
    }

    public function index(Request $request)
    {
        $brands = Brand::with('category')
            ->when($request->filled('search'), fn ($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
            )
            ->when($request->filled('category_id'), fn ($q) =>
                $q->where('category_id', $request->category_id)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $categories = Category::select('id', 'name')->get();

        return Inertia::render('Brands/Index', [
            'brands'     => $brands,
            'filters'    => $request->only('search', 'category_id'),
            'categories' => $categories,
        ]);
    }
    // Hiển thị form tạo thương hiệu
    public function store(StoreBrandRequest $request)
    {
        $this->brandService->create($request->validated());

        return back()->with('success', 'Đã thêm thương hiệu thành công');
    }
    // cập nhật thương hiệu
    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $this->brandService->update($brand, $request->validated());

        return back()->with('success', 'Đã cập nhật thương hiệu thành công');
    }
    // Xóa thương hiệu (chuyển vào thùng rác)
    public function destroy(Brand $brand)
    {
        $brand->delete();
        return back()->with('success', 'Đã chuyển thương hiệu vào thùng rác');
    }
    // Hiển thị danh sách thương hiệu trong thùng rác
    public function trash()
    {
        $brands = Brand::onlyTrashed()->with('category')->latest()->get();
        return response()->json($brands);
    }
    // Khôi phục thương hiệu từ thùng rác
    public function restore($id)
    {
        Brand::withTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Đã khôi phục thương hiệu thành công');
    }
    // Xóa vĩnh viễn thương hiệu
    public function forceDelete($id)
    {
        $brand = Brand::withTrashed()->find($id);

        if (! $brand) {
            return back()->withErrors(['error' => 'Thương hiệu không tồn tại']);
        }

        if ($brand->products()->exists()) {
            return back()->withErrors(['error' => 'Không thể xóa vì còn sản phẩm thuộc thương hiệu này']);
        }

        $brand->forceDelete();
        return back()->with('success', 'Đã xóa vĩnh viễn thương hiệu');
    }
    // Chuyển trạng thái hoạt động của thương hiệu
    public function toggleStatus($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->update(['is_active' => ! $brand->is_active]);
        return back()->with('success', 'Đã cập nhật trạng thái hoạt động');
    }
}
