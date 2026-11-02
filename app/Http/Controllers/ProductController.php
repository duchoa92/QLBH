<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Models\Brand;
use App\Models\Unit;
use App\Services\Product\ProductService;
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
use App\Models\ProductVariant;



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
            'categories' => Category::with('categoryAttributes.values') // 👈 THÊM DÒNG NÀY
                ->select('id', 'name')
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'attributes' => collect($cat->categoryAttributes ?? [])->map(function ($attr) {
                            return [
                                'id' => $attr->id,
                                'name' => $attr->name,
                                'values' => ($attr->values ?? collect())->map(fn ($v) => [
                                    'value' => $v->value
                                ])
                            ];
                        })
                    ];
                }),
            'brands'     => Brand::query()
                ->select('id', 'name', 'category_id')
                ->when($request->filled('category_id'), function ($q) use ($request) {
                    $q->where('category_id', $request->category_id);
                })
                ->orderBy('name')
                ->get(),
            'units'      => Unit::query()
                ->select('id', 'name', 'short_name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Form', [
            'categories' => Category::with([
                'attributes.values' // 👈 BẮT BUỘC PHẢI CÓ
            ])
            ->select('id', 'name')
            ->get(),

            'brands' => Brand::select('id', 'name', 'category_id')->get(),

            'units' => Unit::query()
                ->select('id', 'name', 'short_name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }


    public function previewSku(Request $request)
    {
        $sku = $this->service->previewNextSku(
            $request->category_id,
            $request->brand_id
        );

        return response()->json([
            'sku' => $sku,
        ]);
    }


    // Hiển thị chi tiết sản phẩm
    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'imeis',
            'variants',
        ]);

        if (request()->expectsJson()) {
            return response()->json(['product' => $product]);
        }

        return Inertia::render('Products/Show', [
            'product' => $product,
        ]);

        
    }

    // Lưu sản phẩm mới
    public function store(StoreProductRequest $request)
    {
        $product = $this->service->create($request->validated());

        return back()->with('success', 'Thêm sản phẩm thành công')->with('createdProductId', $product->id);
    }

    // Cập nhật sản phẩm
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->service->update($product, $request->validated());

        return back()->with('success', 'Cập nhật sản phẩm thành công');
    }

    public function bulkUpdatePrice(Request $request)
    {
        if ($request->has('updates')) {
            return $this->updateListedPrices($request);
        }

        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'target' => ['required', 'in:product,variant,imei'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'attribute_name' => ['nullable', 'string', 'max:100', 'required_with:attribute_value'],
            'attribute_value' => ['nullable', 'string', 'max:100', 'required_with:attribute_name'],
        ]);

        if ($data['target'] === 'product' && !empty($data['attribute_name'])) {
            return back()->withErrors([
                'attribute_name' => 'Bộ lọc thuộc tính chỉ áp dụng cho biến thể hoặc IMEI.',
            ]);
        }

        $productIds = collect($data['product_ids'])->unique()->values();
        $price = (float) $data['sell_price'];
        $attributeName = $data['attribute_name'] ?? null;
        $attributeValue = $data['attribute_value'] ?? null;

        $updated = \DB::transaction(function () use ($data, $productIds, $price, $attributeName, $attributeValue) {
            if ($data['target'] === 'product') {
                return Product::query()
                    ->whereIn('id', $productIds)
                    ->update(['sell_price' => $price]);
            }

            $variants = \App\Models\ProductVariant::query()
                ->whereIn('product_id', $productIds)
                ->where('is_active', true)
                ->get();

            if ($attributeName && $attributeValue) {
                $variants = $variants->filter(function ($variant) use ($attributeName, $attributeValue) {
                    return collect($variant->attributes ?? [])->contains(function ($attribute) use ($attributeName, $attributeValue) {
                        $values = is_array($attribute['value'] ?? null)
                            ? $attribute['value']
                            : [$attribute['value'] ?? null];

                        return mb_strtolower(trim((string) ($attribute['name'] ?? ''))) === mb_strtolower(trim($attributeName))
                            && collect($values)->contains(fn ($value) => mb_strtolower(trim((string) $value)) === mb_strtolower(trim($attributeValue)));
                    });
                });
            }

            if ($data['target'] === 'variant') {
                return \App\Models\ProductVariant::query()
                    ->whereIn('id', $variants->pluck('id'))
                    ->update(['sell_price' => $price]);
            }

            $imeis = \App\Models\ProductImei::query()
                ->whereIn('product_id', $productIds)
                ->where('status', \App\Models\ProductImei::STATUS_IN_STOCK)
                ->when($attributeName && $attributeValue, fn ($query) => $query->whereIn('variant_id', $variants->pluck('id')))
                ->get();

            $imeis->each(function ($imei) use ($price) {
                $previousPrice = (float) ($imei->sell_price ?? 0);

                if ($previousPrice === $price) {
                    return;
                }

                $imei->update(['sell_price' => $price]);

                \App\Models\ProductImeiHistory::create([
                    'product_imei_id' => $imei->id,
                    'imei' => $imei->imei,
                    'type' => 'price_update',
                    'reference_type' => Product::class,
                    'reference_id' => $imei->product_id,
                    'cost_price' => $imei->cost_price,
                    'sell_price' => $price,
                    'meta' => [
                        'previous_sell_price' => $previousPrice,
                        'new_sell_price' => $price,
                        'user_id' => auth()->id(),
                        'source' => 'bulk_price_update',
                    ],
                    'note' => 'Cập nhật giá bán hàng loạt',
                    'happened_at' => now(),
                ]);
            });

            return $imeis->count();
        });

        if ($updated === 0) {
            return back()->withErrors([
                'sell_price' => 'Không có dữ liệu phù hợp để cập nhật giá.',
            ]);
        }

        return back()->with('success', "Đã cập nhật giá bán cho {$updated} mục.");
    }

    private function updateListedPrices(Request $request)
    {
        $data = $request->validate([
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.type' => ['required', 'in:product,variant,imei'],
            'updates.*.id' => ['required', 'integer'],
            'updates.*.sell_price' => ['required', 'numeric', 'min:0'],
        ]);

        $updates = collect($data['updates'])
            ->keyBy(fn ($update) => $update['type'] . ':' . $update['id'])
            ->values();

        $updated = \DB::transaction(function () use ($updates) {
            return $updates->sum(function (array $update) {
                $price = (float) $update['sell_price'];

                if ($update['type'] === 'product') {
                    $product = Product::query()->findOrFail($update['id']);

                    if ((float) $product->sell_price === $price) {
                        return 0;
                    }

                    $product->update(['sell_price' => $price]);

                    return 1;
                }

                if ($update['type'] === 'variant') {
                    $variant = \App\Models\ProductVariant::query()
                        ->where('is_active', true)
                        ->findOrFail($update['id']);

                    if ((float) $variant->sell_price === $price) {
                        return 0;
                    }

                    $variant->update(['sell_price' => $price]);

                    return 1;
                }

                $imei = \App\Models\ProductImei::query()
                    ->where('status', \App\Models\ProductImei::STATUS_IN_STOCK)
                    ->findOrFail($update['id']);

                $previousPrice = (float) ($imei->sell_price ?? 0);

                if ($previousPrice === $price) {
                    return 0;
                }

                $imei->update(['sell_price' => $price]);

                \App\Models\ProductImeiHistory::create([
                    'product_imei_id' => $imei->id,
                    'imei' => $imei->imei,
                    'type' => 'price_update',
                    'reference_type' => Product::class,
                    'reference_id' => $imei->product_id,
                    'cost_price' => $imei->cost_price,
                    'sell_price' => $price,
                    'meta' => [
                        'previous_sell_price' => $previousPrice,
                        'new_sell_price' => $price,
                        'user_id' => auth()->id(),
                        'source' => 'price_list_update',
                    ],
                    'note' => 'Cập nhật giá bán từ danh sách sản phẩm',
                    'happened_at' => now(),
                ]);

                return 1;
            });
        });

        return back()->with(
            'success',
            $updated
                ? "Đã cập nhật giá bán cho {$updated} mục."
                : 'Không có giá nào thay đổi.'
        );
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
            'category',
            'brand',
            'variants',
            'imeis',
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
            ->where('is_active', true)
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

        $allowDuplicate = $request->boolean('force');

        $images = [];

        foreach ($request->file('images', []) as $file) {
            $images[] = $file;
        }

        $result = $service->handle($rows, $allowDuplicate, $images);

        return response()->json([
            'success' => $result['success'] ?? true,
            'count' => $result['count'] ?? 0,
            'errors' => $result['errors'] ?? [],
            'error_count' => $result['error_count'] ?? 0,
        ]);

    }



    public function template()
    {
        return Excel::download(new ProductsTemplateExport, 'file_mau_products.xlsx');
    }


    public function previewImport(Request $request, ProductImportService $service)
    {
        $rows = Excel::toArray([], $request->file('file'))[0];
        $images = [];

        foreach ($request->file('images', []) as $file) {
            $images[] = $file;
        }

        $valid = $service->preview($rows, $images);

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


    public function getProductApi($id)
    {
        $product = Product::with([
            'unit:id,name,short_name',
            'conversionUnit:id,name,short_name',
            'variants' => fn ($query) => $query->where('is_active', true),
        ])->find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Không tìm thấy sản phẩm'
            ], 404);
        }

        return response()->json($product);
    }


    // API lấy product + variant
    public function listForImport(Request $request)
    {
        $keyword = trim($request->input('keyword', ''));

        $products = Product::query()
            ->where('is_active', true)

            ->when($keyword, function ($query) use ($keyword) {

                $normalizedKeyword = Product::normalizeSearch($keyword);

                $query->where(function ($q) use (
                    $keyword,
                    $normalizedKeyword
                ) {

                    $q->where('barcode', $keyword)
                        ->orWhere('sku', 'like', $keyword . '%')
                        ->orWhere(
                            'search_text',
                            'like',
                            '%' . $normalizedKeyword . '%'
                        );
                });
            })

            ->with([
                'unit:id,name,short_name',
                'conversionUnit:id,name,short_name',
                'variants' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select('id', 'product_id', 'sku', 'barcode', 'attributes', 'cost_price', 'sell_price', 'stock', 'is_active'),
            ])

            ->select([
                'id',
                'category_id',
                'name',
                'sku',
                'barcode',
                'cost_price',
                'sell_price',
                'stock',
                'product_type',
                'manage_stock_by_serial',
                'image',
                'unit_id',
                'has_unit_conversion',
                'conversion_unit_id',
                'conversion_factor',
            ])

            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json(
            $products
        );
    }

    // API SEARCH
    public function search(Request $request)
    {
        $keyword = $request->q;

        return Product::with([
            'variants' => fn ($query) => $query->where('is_active', true),
        ])
            ->where('name', 'like', "%$keyword%")
            ->orWhere('sku', 'like', "%$keyword%")
            ->limit(10)
            ->get()
            ->flatMap(function ($p) {
                return $p->variants->map(function ($v) use ($p) {
                    return [
                        'product_id' => $p->id,
                        'variant_id' => $v->id,
                        'name' => $p->name,
                        'sku' => $v->sku,
                        'attributes' => $v->attributes,
                        'price' => $v->cost_price,
                        'image' => $p->image_url,
                    ];
                });
            });
    }


}
