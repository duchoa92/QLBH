<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductStockBatch;
use App\Models\StockImportItem;
use App\Support\WarrantyDuration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * API sản phẩm dùng cho POS, phiếu nhập kho và chọn linh kiện sửa chữa.
 * Routes: GET /api/products, /api/products/{id}, /api/products/{id}/imeis.
 */
class ProductController extends Controller
{
    private const LIST_LIMIT = 200;

    private const IMEI_LIMIT = 500;

    /** Các kiểu sắp xếp cho phép ở màn hình bán hàng (khóa => cột và chiều sắp xếp). */
    private const SORTS = [
        'name_asc' => [['name', 'asc']],
        'name_desc' => [['name', 'desc']],
        'best_selling' => [['sold_count', 'desc'], ['name', 'asc']],
        'newest' => [['id', 'desc']],
        'price_asc' => [['sell_price', 'asc'], ['name', 'asc']],
        'price_desc' => [['sell_price', 'desc'], ['name', 'asc']],
        'stock_desc' => [['stock', 'desc'], ['name', 'asc']],
    ];

    /** Danh sách sản phẩm đang bán, lọc theo từ khóa / danh mục và sắp xếp theo lựa chọn. */
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $categoryId = $request->query('category_id');
        $sort = self::SORTS[(string) $request->query('sort', '')] ?? self::SORTS['name_asc'];

        $products = Product::query()
            ->where('is_active', true)
            ->when(filled($categoryId), fn ($query) => $query->where('category_id', (int) $categoryId))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $normalized = Product::normalizeSearch($keyword);

                $query->where(function ($sub) use ($keyword, $normalized): void {
                    $sub->where('model', 'like', '%' . $keyword . '%')
                        ->orWhere('sku', 'like', $keyword . '%')
                        ->orWhere('search_text', 'like', '%' . $normalized . '%')
                        ->orWhereHas('variants', fn ($variants) => $variants
                            ->where('barcode', $keyword)
                            ->orWhere('sku', 'like', $keyword . '%'));
                });
            })
            ->with([
                'unit:id,name,short_name',
                'conversionUnit:id,name,short_name',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->when(true, function ($query) use ($sort): void {
                foreach ($sort as [$column, $direction]) {
                    $query->orderBy($column, $direction);
                }
            })
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json($this->present($products)->values());
    }

    /** Một sản phẩm (dùng sau khi tạo nhanh sản phẩm ở phiếu nhập kho). */
    public function getProductApi(int|string $id): JsonResponse
    {
        $product = Product::query()
            ->with([
                'unit:id,name,short_name',
                'conversionUnit:id,name,short_name',
                'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->findOrFail((int) $id);

        return response()->json($this->present(collect([$product]))->first());
    }

    /** IMEI/Serial còn trong kho của một sản phẩm (POS chọn IMEI), lọc theo biến thể nếu có. */
    public function imeis(Request $request, int|string $id): JsonResponse
    {
        $product = Product::query()->findOrFail((int) $id);
        $variantId = $request->query('variant_id');

        $hasVat = StockImportItem::query()
            ->where('product_id', $product->id)
            ->where('has_vat', true)
            ->where('is_used', false)
            ->exists();

        $imeis = ProductImei::query()
            ->with('variant:id,product_id,sku,barcode,attributes,cost_price,sell_price')
            ->where('product_id', $product->id)
            ->where('status', ProductImei::STATUS_IN_STOCK)
            ->when(filled($variantId), fn ($query) => $query->where('variant_id', (int) $variantId))
            ->orderBy('imei')
            ->limit(self::IMEI_LIMIT)
            ->get();

        return response()->json($imeis->map(function (ProductImei $imei) use ($product, $hasVat): array {
            $extra = is_array($imei->extra_info) ? $imei->extra_info : [];

            return [
                'id' => $imei->id,
                'product_id' => $imei->product_id,
                'variant_id' => $imei->variant_id,
                'variant' => $imei->variant,
                'imei' => $imei->imei,
                'serial' => $imei->serial,
                'display_code' => $imei->imei ?: $imei->serial,
                'color' => $imei->color,
                'storage' => $imei->storage,
                'status' => $imei->status,
                'is_used' => (bool) $imei->is_used,
                // Máy cũ không chịu VAT dù sản phẩm có lô nhập mới có VAT.
                'has_vat' => ! $imei->is_used && $hasVat,
                'cost_price' => (float) $imei->cost_price,
                'sell_price' => (float) $imei->sell_price,
                'effective_sell_price' => $this->effectiveImeiSellPrice($imei, $product),
                // BH khách theo lúc nhập máy này; chưa có thì dùng lần bán gần nhất rồi tới BH của sản phẩm.
                'warranty_days' => (int) (WarrantyDuration::daysFromImportExtra($extra) ?? $imei->customer_warranty_days ?? $product->warranty_days ?? 0),
                // Chỉ trả ghi chú; tài khoản/mật khẩu trong extra_info không được lộ ra POS.
                'extra_info' => (string) ($extra['note'] ?? ''),
            ];
        })->values());
    }

    private function effectiveImeiSellPrice(ProductImei $imei, Product $product): float
    {
        if ((float) $imei->sell_price > 0) {
            return (float) $imei->sell_price;
        }

        if ((float) ($imei->variant?->sell_price ?? 0) > 0) {
            return (float) $imei->variant->sell_price;
        }

        return (float) $product->sell_price;
    }

    /**
     * Gắn lô tồn, khoảng giá IMEI và cờ VAT cho cả danh sách chỉ với vài truy vấn gộp.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function present(Collection $products): Collection
    {
        $ids = $products->pluck('id');

        $batchRows = ProductStockBatch::query()
            ->whereIn('product_id', $ids)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('id')
            ->get(['id', 'product_id', 'variant_id', 'stock_import_item_id', 'remaining_quantity', 'cost_price', 'sell_price', 'is_used', 'imported_at']);

        // BH khách ghi trên dòng nhập của từng lô: POS dùng đúng thời hạn lúc nhập khi bán lô đó.
        $importWarranty = StockImportItem::query()
            ->whereIn('id', $batchRows->pluck('stock_import_item_id')->filter()->unique())
            ->get(['id', 'extra_info'])
            ->mapWithKeys(fn (StockImportItem $item): array => [$item->id => WarrantyDuration::daysFromImportExtra($item->extra_info)]);
        $batchRows->each(fn (ProductStockBatch $batch) => $batch->setAttribute('customer_warranty_days', $importWarranty->get($batch->stock_import_item_id)));

        $batches = $batchRows->groupBy('product_id');

        // Phạm vi (biến thể, 0 = cấp sản phẩm) đã từng có lô, kể cả lô đã hết hàng. Backend kiểm
        // tồn theo lô cho các phạm vi này, nên giao diện không được quay về dùng cột tồn khi hết lô.
        $batchScopes = ProductStockBatch::query()
            ->whereIn('product_id', $ids)
            ->select('product_id', 'variant_id')
            ->distinct()
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => (int) ($row->variant_id ?? 0))->unique()->values());

        // Giá ngoài danh sách phải khớp giá từng máy trong cửa sổ chọn IMEI: dùng cùng giá hiệu lực
        // (giá máy -> giá biến thể -> giá sản phẩm) và chỉ tính máy còn trong kho.
        $productsById = $products->keyBy('id');
        $imeiPrices = ProductImei::query()
            ->with('variant:id,sell_price')
            ->whereIn('product_id', $ids)
            ->where('status', ProductImei::STATUS_IN_STOCK)
            ->get(['id', 'product_id', 'variant_id', 'sell_price', 'cost_price', 'is_used'])
            ->groupBy('product_id')
            ->map(function ($rows, $productId) use ($productsById) {
                $product = $productsById->get($productId);
                $sell = $rows->map(fn (ProductImei $imei) => $product ? $this->effectiveImeiSellPrice($imei, $product) : (float) $imei->sell_price)
                    ->filter(fn ($price) => $price > 0);
                $cost = $rows->map(fn (ProductImei $imei) => (float) $imei->cost_price > 0
                    ? (float) $imei->cost_price
                    : (float) ($product?->cost_price ?? 0))->filter(fn ($price) => $price > 0);

                return (object) [
                    'price_min' => $sell->min() ?? 0,
                    'price_max' => $sell->max() ?? 0,
                    'cost_min' => $cost->min() ?? 0,
                    'cost_max' => $cost->max() ?? 0,
                    'total_count' => $rows->count(),
                    'used_count' => $rows->where('is_used', true)->count(),
                ];
            });

        $vatProductIds = StockImportItem::query()
            ->whereIn('product_id', $ids)
            ->where('has_vat', true)
            ->where('is_used', false)
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        return $products->map(function (Product $product) use ($batches, $batchScopes, $imeiPrices, $vatProductIds): array {
            $isImei = $product->product_type === 'imei' || (bool) $product->manage_stock_by_serial;
            $unitName = $product->unit?->short_name ?: $product->unit?->name;
            $prices = $imeiPrices->get($product->id);

            $priceMin = $isImei && $prices && (float) $prices->price_min > 0 ? (float) $prices->price_min : null;
            $priceMax = $isImei && $prices && (float) $prices->price_max > 0 ? (float) $prices->price_max : null;
            $costMin = $isImei && $prices && (float) $prices->cost_min > 0 ? (float) $prices->cost_min : null;
            $costMax = $isImei && $prices && (float) $prices->cost_max > 0 ? (float) $prices->cost_max : null;

            return [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'model' => $product->model,
                'image' => $product->image,
                'image_url' => $product->image_url,
                'product_type' => $product->product_type,
                'manage_stock_by_serial' => (bool) $product->manage_stock_by_serial,
                'has_imei' => $isImei,
                'has_variants' => $product->variants->isNotEmpty(),
                'stock' => (int) $product->stock,
                'price' => (float) $product->sell_price,
                'sell_price' => (float) $product->sell_price,
                'cost_price' => (float) $product->cost_price,
                'price_min' => $priceMin,
                'price_max' => $priceMax,
                'cost_price_min' => $costMin,
                'cost_price_max' => $costMax,
                'imei_in_stock_count' => $isImei ? (int) ($prices->total_count ?? 0) : 0,
                'imei_used_count' => $isImei ? (int) ($prices->used_count ?? 0) : 0,
                'unit_id' => $product->unit_id,
                'unit_name' => $unitName,
                'unit' => $product->unit,
                'inventory_unit_name' => $unitName,
                'has_unit_conversion' => (bool) $product->has_unit_conversion,
                'conversion_unit_id' => $product->conversion_unit_id,
                'conversion_unit' => $product->conversionUnit,
                'conversion_factor' => (int) ($product->conversion_factor ?: 1),
                'warranty_days' => (int) $product->warranty_days,
                'tax_percent' => (float) ($product->tax_percent ?? 0),
                'has_vat' => $vatProductIds->has((int) $product->id),
                'sold_count' => (int) ($product->sold_count ?? 0),
                'variants' => $product->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'attributes' => $variant->attributes,
                    'cost_price' => (float) $variant->cost_price,
                    'sell_price' => (float) $variant->sell_price,
                    'price' => (float) $variant->sell_price,
                    'stock' => (int) $variant->stock,
                    'is_active' => (bool) $variant->is_active,
                ])->values(),
                'batch_scopes' => $isImei ? [] : ($batchScopes->get($product->id) ?? collect())->values(),
                // IMEI tự quản lý giá theo từng máy nên không có lô FIFO.
                'stock_batches' => $isImei
                    ? []
                    : $this->mergeBatchesBySellPrice($batches->get($product->id) ?? collect()),
            ];
        });
    }
    /**
     * Gộp các lô cùng biến thể, cùng giá bán, cùng tình trạng (mới/cũ) và cùng BH khách thành một dòng để POS không
     * tách nhiều dòng giống hệt nhau. Dòng gộp lấy id và giá vốn của lô cũ nhất, số lượng là tổng.
     * Server vẫn trừ kho theo FIFO từng lô nên việc gộp ở đây chỉ ảnh hưởng cách hiển thị và chọn hàng.
     *
     * @param  Collection<int, ProductStockBatch>  $batches
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeBatchesBySellPrice(Collection $batches): Collection
    {
        return $batches
            ->sortBy('id')
            ->groupBy(fn (ProductStockBatch $batch): string => ((int) $batch->variant_id).'|'.number_format((float) $batch->sell_price, 2, '.', '').'|'.($batch->is_used ? '1' : '0').'|'.($batch->customer_warranty_days ?? 'p'))
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'id' => $first->id,
                    'product_id' => $first->product_id,
                    'variant_id' => $first->variant_id,
                    'remaining_quantity' => (int) $group->sum('remaining_quantity'),
                    'cost_price' => (float) $group->min('cost_price'),
                    'cost_price_max' => (float) $group->max('cost_price'),
                    'sell_price' => (float) $first->sell_price,
                    'is_used' => (bool) $first->is_used,
                    // null = lúc nhập không ghi BH riêng → POS dùng BH khai báo ở sản phẩm.
                    'customer_warranty_days' => $first->customer_warranty_days,
                    'imported_at' => $first->imported_at?->toDateString(),
                    'batch_ids' => $group->pluck('id')->values(),
                ];
            })
            ->sortBy('id')
            ->values();
    }
}
