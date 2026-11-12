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
use App\Services\ProductImportService;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ExportProductsJob;
use App\Exports\ImportErrorExport;
use App\Models\ProductVariant;
use App\Models\StockImportItem;
use App\Models\ExportHistory;
use App\Services\ProductImportException;
use App\Services\ProductImportImageStore;



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
            'brands'     => Brand::optionsWithCategories(
                $request->filled('category_id') ? (int) $request->category_id : null
            ),
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

            'brands' => Brand::optionsWithCategories(),

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
                $productCount = Product::query()
                    ->whereIn('id', $productIds)
                    ->update(['sell_price' => $price]);
                $batchCount = \App\Models\ProductStockBatch::query()
                    ->whereIn('product_id', $productIds)
                    ->whereNull('variant_id')
                    ->where('remaining_quantity', '>', 0)
                    ->update(['sell_price' => $price]);

                return max($productCount, $batchCount);
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
                $variantIds = $variants->pluck('id');
                $variantCount = \App\Models\ProductVariant::query()
                    ->whereIn('id', $variantIds)
                    ->update(['sell_price' => $price]);
                $batchCount = \App\Models\ProductStockBatch::query()
                    ->whereIn('variant_id', $variantIds)
                    ->where('remaining_quantity', '>', 0)
                    ->update(['sell_price' => $price]);

                return max($variantCount, $batchCount);
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
                    $product->update(['sell_price' => $price]);
                    $batches = \App\Models\ProductStockBatch::query()
                        ->where('product_id', $product->id)
                        ->whereNull('variant_id')
                        ->where('remaining_quantity', '>', 0)
                        ->update(['sell_price' => $price]);

                    return $batches > 0 || (float) $product->getOriginal('sell_price') !== $price ? 1 : 0;
                }

                if ($update['type'] === 'variant') {
                    $variant = \App\Models\ProductVariant::query()
                        ->where('is_active', true)
                        ->findOrFail($update['id']);

                    $variant->update(['sell_price' => $price]);
                    $batches = \App\Models\ProductStockBatch::query()
                        ->where('variant_id', $variant->id)
                        ->where('remaining_quantity', '>', 0)
                        ->update(['sell_price' => $price]);

                    return $batches > 0 || (float) $variant->getOriginal('sell_price') !== $price ? 1 : 0;
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
            $usage = $product->deletionUsage();
            $parts = array_filter([
                $usage['sale_items'] ? "{$usage['sale_items']} dòng hóa đơn" : null,
                $usage['gift_items'] ? "{$usage['gift_items']} dòng quà tặng" : null,
                $usage['import_items'] ? "{$usage['import_items']} dòng phiếu nhập" : null,
                $usage['imeis'] ? "{$usage['imeis']} IMEI/Serial" : null,
                $usage['repair_parts'] ? "{$usage['repair_parts']} dòng linh kiện sửa chữa" : null,
            ]);
            return back()->withErrors(['error' => 'Không thể xóa vĩnh viễn sản phẩm vì đã có '.implode(', ', $parts).'. Đây là dữ liệu lịch sử; hãy giữ sản phẩm trong thùng rác hoặc ngừng hoạt động, không xóa các hóa đơn/phiếu nhập chỉ để xóa sản phẩm.']);
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
            return back()->withErrors(['error' => "Có {$skippedCount} sản phẩm đã phát sinh hóa đơn, phiếu nhập, IMEI/Serial hoặc phiếu sửa nên không thể xóa vĩnh viễn. Các sản phẩm còn lại đã được xóa. Hãy giữ các sản phẩm này trong thùng rác hoặc ngừng hoạt động; không xóa chứng từ lịch sử chỉ để xóa sản phẩm."]);
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

/*================ Nhập xuất file excel ================================ */

    /** Quy tắc kiểm tra file tải lên của bước kiểm tra và bước nhập. */
    private function importRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'image_token' => ['nullable', 'uuid'],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    private function importMessages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file Excel.',
            'file.mimes' => 'Chỉ nhận file .xlsx, .xls hoặc .csv.',
            'file.max' => 'File quá lớn (tối đa 10MB).',
            'images.max' => 'Mỗi lần chỉ gửi tối đa 20 ảnh trực tiếp.',
            'images.*.image' => 'Có file ảnh không hợp lệ.',
            'images.*.mimes' => 'Ảnh chỉ nhận jpg, png, webp, gif.',
            'images.*.max' => 'Mỗi ảnh tối đa 5MB.',
        ];
    }

    private function readSpreadsheetRows(Request $request): array
    {
        try {
            return Excel::toArray([], $request->file('file'))[0] ?? [];
        } catch (\Throwable $e) {
            report($e);

            throw new ProductImportException('Không đọc được file. Hãy dùng file mẫu (.xlsx) và chắc chắn file không bị hỏng hoặc đặt mật khẩu.');
        }
    }

    /** @return array<int, mixed> */
    private function importImages(Request $request, ProductImportImageStore $imageStore): array
    {
        if ($request->filled('image_token')) {
            return $imageStore->resolve((string) $request->input('image_token'), (int) $request->user()->id);
        }

        return array_values($request->file('images', []));
    }

    public function import(Request $request, ProductImportService $service, ProductImportImageStore $imageStore)
    {
        $request->validate($this->importRules(), $this->importMessages());

        // Nhập nhiều dòng + lưu ảnh có thể lâu hơn mặc định của PHP.
        @set_time_limit(300);

        try {
            $rows = $this->readSpreadsheetRows($request);
            $images = $this->importImages($request, $imageStore);
            $result = $service->handle($rows, $images);
        } catch (ProductImportException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // Dọn ảnh tạm sau khi nhập xong (thành công hoặc đã hoàn tác thì người dùng cũng phải xem trước lại).
        if ($request->filled('image_token') && ($result['success'] ?? false)) {
            $imageStore->forget((string) $request->input('image_token'));
        }

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

    public function previewImport(Request $request, ProductImportService $service, ProductImportImageStore $imageStore)
    {
        $request->validate($this->importRules(), $this->importMessages());

        @set_time_limit(300);

        try {
            $rows = $this->readSpreadsheetRows($request);
            $images = $this->importImages($request, $imageStore);
            $valid = $service->preview($rows, $images);
        } catch (ProductImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'valid' => $valid,
            'error_count' => collect($valid)->where('is_error', true)->count()
        ]);
    }

    /** Tải ảnh lên theo từng đợt nhỏ; trả về token dùng cho bước kiểm tra/nhập. */
    public function uploadImportImages(Request $request, ProductImportImageStore $imageStore)
    {
        $request->validate([
            'token' => ['nullable', 'uuid'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ], [
            'images.*.image' => 'Có file ảnh không hợp lệ.',
            'images.*.mimes' => 'Ảnh chỉ nhận jpg, png, webp, gif.',
            'images.*.max' => 'Mỗi ảnh tối đa 5MB.',
        ]);

        $token = (string) ($request->input('token') ?: $imageStore->newToken());

        try {
            $count = $imageStore->add($token, array_values($request->file('images', [])), (int) $request->user()->id);
        } catch (ProductImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['token' => $token, 'count' => $count]);
    }

    public function startExport()
    {
        try {

            $export = ExportHistory::create([
                'type' => 'products'
            ]);

            \App\Jobs\ExportProductsJob::dispatch($export->id);

            return response()->json([
                'id' => $export->id
            ]);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'error' => 'Không thể bắt đầu xuất file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkExport($id)
    {
        $export = ExportHistory::where('type', 'products')->findOrFail($id);

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status,
            'file' => $export->file
        ]);
    }

    // Tải file trích xuất
    public function downloadExport($id)
    {
        $export = ExportHistory::where('type', 'products')->findOrFail($id);

        if ($export->status !== 'done' || !$export->file) {
            return response()->json([
                'error' => 'File chưa sẵn sàng'
            ], 404);
        }

        if (!Storage::disk('local')->exists($export->file)) {
            return response()->json([
                'error' => 'File không còn tồn tại, vui lòng xuất lại'
            ], 404);
        }

        return Storage::disk('local')->download(
            $export->file,
            'san_pham_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    // Tải file lỗi
    public function exportErrors(Request $request)
    {
        $request->validate([
            'errors' => ['required', 'array', 'min:1', 'max:5000'],
            'errors.*' => ['array'],
        ], [
            'errors.required' => 'Không có lỗi để xuất.',
        ]);

        return Excel::download(
            new ImportErrorExport($request->input('errors', [])),
            'loi_import.xlsx'
        );
    }


    // API lấy product + variant
    public function listForImport(Request $request)
    {
        $keyword = trim($request->input('keyword', ''));
        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->where('is_active', true)

            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('id', $ids))

            ->when($keyword, function ($query) use ($keyword) {

                $normalizedKeyword = Product::normalizeSearch($keyword);

                $query->where(function ($q) use (
                    $keyword,
                    $normalizedKeyword
                ) {

                    $q->where('model', 'like', '%' . $keyword . '%')
                        ->orWhere('sku', 'like', $keyword . '%')
                        ->orWhere(
                            'search_text',
                            'like',
                            '%' . $normalizedKeyword . '%'
                        )
                        ->orWhereHas('variants', fn ($variantQuery) => $variantQuery
                            ->where('barcode', $keyword)
                            ->orWhere('sku', 'like', $keyword . '%'));
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
                'model',
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

        // Lấy BH khách của lần nhập gần nhất, gồm cả BH theo biến thể. Giá trị
        // này chỉ là mặc định cho biểu mẫu mới, người dùng vẫn có thể thay đổi.
        $warrantyByProduct = [];
        $warrantyByVariant = [];
        StockImportItem::query()
            ->select('stock_import_items.product_id', 'stock_import_items.variant_id', 'stock_import_items.extra_info')
            ->whereIn('stock_import_items.product_id', $products->pluck('id'))
            ->whereHas('stockImport', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->orderByDesc('stock_import_items.id')
            ->get()
            ->each(function (StockImportItem $item) use (&$warrantyByProduct, &$warrantyByVariant): void {
                $extra = is_array($item->extra_info) ? $item->extra_info : [];
                $value = (int) ($extra['customer_warranty_duration_value'] ?? 0);
                $unit = $extra['customer_warranty_duration_unit'] ?? 'months';
                if ($value <= 0 || ! in_array($unit, ['days', 'months'], true)) return;

                $warranty = ['value' => $value, 'unit' => $unit];
                $productKey = (int) $item->product_id;
                $variantKey = $item->variant_id ? (int) $item->variant_id : null;
                if (! isset($warrantyByProduct[$productKey])) $warrantyByProduct[$productKey] = $warranty;
                if ($variantKey && ! isset($warrantyByVariant[$variantKey])) $warrantyByVariant[$variantKey] = $warranty;
            });

        $products->each(function (Product $product) use ($warrantyByProduct, $warrantyByVariant): void {
            $productWarranty = $warrantyByProduct[(int) $product->id] ?? null;
            $product->setAttribute('last_customer_warranty', $productWarranty);
            $product->variants->each(function (ProductVariant $variant) use ($productWarranty, $warrantyByVariant): void {
                $variant->setAttribute('last_customer_warranty', $warrantyByVariant[(int) $variant->id] ?? $productWarranty);
            });
        });

        return response()->json(
            $products
        );
    }

}
