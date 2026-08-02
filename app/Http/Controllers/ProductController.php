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
use App\Exports\ProductsTemplateExport;
use App\Exports\ProductsDataExport;
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ExportProductsJob;
use Illuminate\Support\Str;
use App\Exports\ImportErrorExport;



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
   public function import(Request $request, ProductImportService $service)
    {
        $rows = Excel::toArray([], $request->file('file'))[0];

        $allowDuplicate = $request->get('force', false);

        $images = [];

        foreach ($request->file('images') as $file) {


            $images[] = $file;
        }

        $result = $service->handle($rows, $allowDuplicate, $images);

        return response()->json([
            'count' => $result['count'] ?? 0,
            'errors' => $result['errors'] ?? [],
            'error_count' => $result['error_count'] ?? 0,
        ]);

    }



    public function template()
    {
        return Excel::download(new ProductsTemplateExport, 'file_mau_products.xlsx');
    }


    public function previewImport(Request $request)
    {
        $rows = Excel::toArray([], $request->file('file'))[0];

        $valid = [];

        // LẤY HEADER (QUAN TRỌNG)
        $header = array_map(fn($h) => strtolower(trim($h)), $rows[0]);

        foreach ($rows as $index => $row) {

            if ($index === 0) continue;

            $name         = trim($row[1] ?? '');
            $sku          = trim($row[2] ?? '');
            $barcode      = trim($row[3] ?? '');
            $categoryName = trim($row[4] ?? '');
            $brandName    = trim($row[5] ?? '');
            $sellPrice    = trim($row[6] ?? '');

            $costPrice    = trim($row[7] ?? '');
            $stock        = trim($row[8] ?? '');

            $type         = $row[9] ?? 'normal';
            $active       = $row[10] ?? 1;
            $rawImageName = trim($row[11] ?? '');

            $imageName = strtolower(
                preg_replace('/[^a-z0-9]/', '', pathinfo($rawImageName, PATHINFO_FILENAME))
            );
            $rowNumber = $index + 1;

            $status = 'OK';
            $isError = false;

            /* ===== VALIDATE ===== */

            if (!$name) {
                $status = 'Thiếu tên';
                $isError = true;
            }
            elseif (!$sku) {
                $status = 'Thiếu SKU';
                $isError = true;
            }
            elseif ($sellPrice === '' || $sellPrice === null || !is_numeric($sellPrice)) {
                $status = 'Thiếu giá';
                $isError = true;
            }
            // cost_price optional
            elseif ($costPrice !== '' && !is_numeric($costPrice)) {
                $status = 'Giá nhập không hợp lệ';
                $isError = true;
            }

            // stock optional
            elseif ($stock !== '' && !is_numeric($stock)) {
                $status = 'Tồn kho không hợp lệ';
                $isError = true;
            }
            elseif (!in_array($type, ['normal','imei','service','combo'])) {
                $status = 'Sai loại';
                $isError = true;
            }
            elseif ($sku && Product::where('sku', $sku)->exists()) {
                $status = 'Trùng SKU';
                $isError = true;
            }

            /* ===== CHECK ẢNH ===== */
            if (!$isError && $rawImageName) {
                $imageFiles = [];

                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $file) {

                        $fileName = strtolower(
                            preg_replace('/[^a-z0-9]/', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                        );

                        $imageFiles[$fileName] = $file;
                    }
                }

                $found = false;

                foreach ($imageFiles as $key => $originalName) {

                    // match gần đúng
                    if (
                        str_contains($key, $imageName) ||
                        str_contains($imageName, $key)
                    ) {
                        $imageName = $originalName; // trả về đúng tên file upload
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $status = 'Thiếu ảnh';
                    $isError = true;
                }
            }

            $valid[] = [
                'row' => $rowNumber,
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'category' => $categoryName,
                'brand' => $brandName,
                'sell_price' => $sellPrice,
                'cost_price' => $costPrice,
                'stock' => $stock,
                'status' => $status,
                'image_name' => $rawImageName,
                'is_error' => $isError
            ];
        }

        return response()->json([
            'valid' => $valid,
            'error_count' => collect($valid)->where('is_error', true)->count()
        ]);
    }


    
    public function startExport()
    {
        try {

            $export = \App\Models\ExportHistory::create([
                'type' => 'products'
            ]);

            \App\Jobs\ExportProductsJob::dispatch($export->id);

            return response()->json([
                'id' => $export->id
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkExport($id)
    {
        $export = \App\Models\ExportHistory::findOrFail($id);

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status,
            'file' => $export->file
        ]);
    }

    // Tải file trích xuất
    public function downloadExport($id)
    {
        $export = \App\Models\ExportHistory::findOrFail($id);

        if (!$export->file) {
            return response()->json([
                'error' => 'Chưa có file'
            ], 404);
        }

        if (!Storage::disk('local')->exists($export->file)) {
            return response()->json([
                'error' => 'File không tồn tại: ' . $export->file
            ], 404);
        }

        return Storage::disk('local')->download($export->file);
    }

    // Tải file lỗi
    public function exportErrors(Request $request)
    {
        $errors = $request->input('errors', []);

        if (!$errors || !count($errors)) {
            return response()->json(['error' => 'Không có lỗi'], 400);
        }

        return Excel::download(
            new ImportErrorExport($errors),
            'loi_import.xlsx'
        );
    }


}