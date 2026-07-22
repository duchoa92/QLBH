<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Product\ProductService;
use App\Repositories\Product\ProductRepository;
use App\Models\Brand;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductsImport;
use App\Exports\ProductsExport;
use App\Exports\ProductsExportReal;


class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected ProductRepository $productRepository
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'category_id',
            'brand_id',
            'stock',
            'sort_by',
            'sort_order',
            'per_page'
        ]);

        return Inertia::render('Products/Index', [
            'products'   => $this->productRepository->paginate(),
            'filters'    => $filters,
            'categories' => Category::select('id', 'name')->get(),
            'brands'     => Brand::query()
                ->select('id', 'name', 'category_id')
                ->when($request->filled('category_id'), function ($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    // Hiển thị chi tiết sản phẩm
    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'imeis',
        ]);

        return Inertia::render('Products/Show', [
            'product' => $product,
        ]);
    }

    // Lưu sản phẩm mới
    public function store(StoreProductRequest $request)
    {
        $this->service->create($request->validated());

        return back()->with('success', 'Thêm sản phẩm thành công');
    }

    // Cập nhật sản phẩm
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->service->update($product, $request->validated());

        return back()->with('success', 'Cập nhật sản phẩm thành công');
    }

    public function toggleStatus(Product $product)
    {
        $product->update([
            'is_active' => !$product->is_active
        ]);
        return back()->with('success', 'Cập nhật trạng thái thành công');
    }

    // Chuyển vào thùng rác
    public function destroy(Product $product)
    {
        $this->service->delete($product);

        return back()->with('success', 'Đã chuyển vào thùng rác!');
    }

    // Thùng rác
     public function trash(Request $request)
    {
        $products = Product::onlyTrashed()
            ->with(['category:id,name', 'brand:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count()
            ]
        ]);
    }

    // Khôi phục sản phẩm
    public function restore($id)
    {
        Product::onlyTrashed()->findOrFail($id)->restore();

        return back()->with('success', 'Đã khôi phục sản phẩm thành công');
    }

    // Kiểm tra an toàn trước khi xóa vĩnh viễn (Đồng bộ chuẩn back()->withErrors)
    public function forceDelete($id)
    {
        $product = Product::withTrashed()->find($id);

        if (!$product) {
            return back()->withErrors(['error' => 'Sản phẩm không tồn tại']);
        }

        if (!$product->canForceDelete()) {
            return back()->withErrors(['error' => 'Sản phẩm đã phát sinh dữ liệu, không thể xóa vĩnh viễn']);
        }

        $product->forceDelete();

        return back()->with('success', 'Đã xóa vĩnh viễn sản phẩm');
    }

    // Bulk restore
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        Product::onlyTrashed()
            ->whereIn('id', $request->ids)
            ->restore();

        return back()->with('success', 'Khôi phục các sản phẩm thành công');
    }

    // Bulk force delete (Đồng bộ kiểm tra hàng loạt)
    public function bulkForceDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        $products = Product::withTrashed()
            ->whereIn('id', $request->ids)
            ->get();

        $skippedCount = 0;
        foreach ($products as $product) {
            if ($product->canForceDelete()) {
                $product->forceDelete();
            } else {
                $skippedCount++;
            }
        }

        if ($skippedCount > 0) {
            return back()->withErrors(['error' => "Có {$skippedCount} sản phẩm đã phát sinh dữ liệu không thể xóa vĩnh viễn. Các sản phẩm còn lại đã được xóa."]);
        }

        return back()->with('success', 'Đã xóa vĩnh viễn các sản phẩm được chọn');
    }

    // Xóa nhiều SP tạm thời
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        Product::whereIn('id', $request->ids)->delete();

        return back()->with('success', 'Đã chuyển các sản phẩm vào thùng rác');
    }

    // In tem
    public function printImei(Request $request)
    {
        $ids = $request->get('ids', []);

        $products = Product::whereIn('id', $ids)->get();

        return Inertia::render('Products/PrintImei', [
            'products' => $products
        ]);
    }

    public function printData(Request $request)
    {
        $ids = $request->get('ids', []);

        $products = Product::with([
            'imeis',
            'variants'
        ])->whereIn('id', $ids)->get();

        return response()->json($products);
    }

    // Tạo API Scan
    public function scan(Request $request)
    {
        $code = $request->get('code');

        // 1. tìm theo IMEI
        $imei = \App\Models\ProductImei::with('variant.product')
            ->where('imei', $code)
            ->first();

        if ($imei) {
            return response()->json([
                'type' => 'imei',
                'product' => $imei->variant->product,
                'variant' => $imei->variant
            ]);
        }

        // 2. tìm theo barcode variant
        $variant = \App\Models\ProductVariant::with('product')
            ->where('barcode', $code)
            ->first();

        if ($variant) {
            return response()->json([
                'type' => 'variant',
                'product' => $variant->product,
                'variant' => $variant
            ]);
        }

        // 3. fallback product
        $product = \App\Models\Product::where('barcode', $code)->first();

        if ($product) {
            return response()->json([
                'type' => 'product',
                'product' => $product
            ]);
        }

        return response()->json([
            'error' => 'Không tìm thấy'
        ], 404);


    }

/*================ Nhập xuất file excel ================================ */
   public function import(Request $request)
    {
        Excel::import(new ProductsImport, $request->file('file'));

        return back()->with('success', 'Import thành công');
    }


    public function exportData()
    {
        return Excel::download(new ProductsExportReal, 'products_data.xlsx');
    }

    public function template()
    {
        return Excel::download(new ProductsExport, 'product_file_mau.xlsx');
    }


    public function previewImport(Request $request)
    {
        $rows = Excel::toArray([], $request->file('file'))[0];

        $errors = [];
        $valid = [];

        foreach ($rows as $index => $row) {

            if ($index === 0) continue;

            $name = $row[0] ?? null;
            $sku = $row[1] ?? null;
            $price = $row[2] ?? null;

            if (!$name) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => 'Thiếu tên'
                ];
                continue;
            }

            if (!is_numeric($price)) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => 'Giá sai'
                ];
                continue;
            }

            $valid[] = [
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
            ];
        }

        return response()->json([
            'valid' => $valid,
            'errors' => $errors
        ]);
    }



}