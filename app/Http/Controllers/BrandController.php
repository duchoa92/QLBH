<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\Brand\BrandService; // Thêm Service
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    protected $brandService;

    // Khởi tạo và Inject Service vào đây
    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(Request $request)
    {
        // Kiểm tra xem request có thực sự có giá trị filter hay không
        $brands = Brand::with('category')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
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
        $request->validate([
            'name' => ['required', Rule::unique('brands', 'name')],
            'category_id' => 'required|exists:categories,id'
        ], [
            'name.required' => 'Vui lòng nhập tên thương hiệu',
            'name.unique' => 'Tên thương hiệu đã tồn tại',
            'category_id.required' => 'Vui lòng chọn danh mục'
        ]);

        // SỬ DỤNG SERVICE ĐÃ VIẾT
        $this->brandService->create($request->all());

        return back()->with('success', 'Đã thêm thương hiệu thành công');
    }

    public function update(Request $request, Brand $brand)
    {
         $request->validate([
            'name' => [
                'required',
                Rule::unique('brands', 'name')->ignore($brand->id)
            ],
            'category_id' => 'required|exists:categories,id'
        ], [
            'name.required' => 'Vui lòng nhập tên thương hiệu',
            'name.unique' => 'Tên thương hiệu đã tồn tại',
            'category_id.required' => 'Vui lòng chọn danh mục'
        ]);

        // SỬ DỤNG SERVICE ĐÃ VIẾT
        $this->brandService->update($brand, $request->all());

        return back()->with('success', 'Đã cập nhật thương hiệu thành công');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return back()->with('success', 'Đã chuyển thương hiệu vào thùng rác');
    }

    public function trash()
    {
        // Lấy dữ liệu qua mối quan hệ từ Soft Delete
        $brands = Brand::onlyTrashed()->with('category')->latest()->get();
        return response()->json($brands);
    }

    public function restore($id)
    {
        Brand::withTrashed()->findOrFail($id)->restore();
        // Dùng Inertia reload để cập nhật bảng chính ở ngoài màn hình luôn
        return back()->with('success', 'Đã khôi phục thương hiệu thành công');
    }

    public function forceDelete($id)
    {
        $brand = Brand::withTrashed()->find($id);

        if (!$brand) {
            return back()->withErrors(['error' => 'Thương hiệu không tồn tại']);
        }

        if ($brand->products()->exists()) {
            return back()->withErrors(['error' => 'Không thể xóa vì còn sản phẩm thuộc thương hiệu này']);
        }

        $brand->forceDelete();
        return back()->with('success', 'Đã xóa vĩnh viễn thương hiệu');
    }

    public function toggleStatus($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->is_active = !$brand->is_active;
        $brand->save();

        return back()->with('success', 'Đã cập nhật trạng thái hoạt động');
    }
}