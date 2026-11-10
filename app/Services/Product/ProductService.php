<?php

namespace App\Services\Product;

use App\Models\CategoryAttribute;
use App\Services\Stock\OpeningStockBatchService;
use App\Models\CategoryAttributeValue;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use App\Services\Stock\ImeiStockSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService extends BaseService
{

    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    private function generateCombinations($attributes)
    {
        $result = [[]];

        foreach ($attributes as $attr) {
            $tmp = [];

            foreach ($result as $res) {
                foreach ($attr['value'] as $val) {
                    $tmp[] = array_merge($res, [
                        $attr['name'] => $val
                    ]);
                }
            }

            $result = $tmp;
        }

        return $result;
    }

    private function buildVariantSku($baseSku, $combo)
    {
        $suffix = collect($combo)
            ->map(fn($v) => strtoupper(substr($v, 0, 3)))
            ->join('-');

        return $baseSku . '-' . $suffix;
    }

    public function create(array $data): Model
    {
        // SKU tự nhập (sku_manual) thì giữ nguyên; không thì server tự cấp số kế tiếp ngay lúc lưu.
        // Không gửi sku_manual (API cũ) thì coi SKU có giá trị là SKU tự nhập.
        $skuIsManual = array_key_exists('sku_manual', $data)
            ? filter_var($data['sku_manual'], FILTER_VALIDATE_BOOLEAN)
            : filled($data['sku'] ?? null);
        unset($data['sku_manual']);

        // Lưu ảnh một lần, không lưu lại mỗi lần thử.
        if (isset($data['image'])) {
            $data['image'] = $data['image']->store('products', 'public');
        }

        $maxAttempts = $skuIsManual ? 1 : 3;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createProduct($data, $skuIsManual);
            } catch (QueryException $exception) {
                // 23000 = vi phạm ràng buộc duy nhất: hai người tạo cùng lúc có thể lấy trùng số SKU kế tiếp.
                $isDuplicate = (string) $exception->getCode() === '23000';

                if (! $isDuplicate || $attempt >= $maxAttempts) {
                    throw $exception;
                }
            }
        }
    }

    private function createProduct(array $data, bool $skuIsManual): Model
    {
        return DB::transaction(function () use ($data, $skuIsManual) {
            $variants = $data['variants'] ?? [];
            $imeis = $data['imeis'] ?? null;

            unset($data['variants'], $data['imeis']);

            $data = $this->normalizeProductData($data);
            $data['slug'] = $this->generateUniqueSlug($data['name']);
            $data['model'] = $data['model'] ?? null;

            if (! $skuIsManual || empty($data['sku'])) {
                $data['sku'] = $this->nextProductSku(
                    isset($data['category_id']) ? (int) $data['category_id'] : null,
                    isset($data['brand_id']) ? (int) $data['brand_id'] : null
                );
            }

            $product = $this->repository->create($data);

            $this->syncVariants($product, $variants);
            $this->syncProductImeis($product, $imeis);

            // Tồn nhập trực tiếp khi tạo sản phẩm cần có lô tồn đầu kỳ, nếu không bán hàng
            // (trừ kho theo lô) sẽ báo "Không đủ tồn kho theo lô".
            if (! $product->manage_stock_by_serial && empty($variants) && (int) $product->stock > 0) {
                app(OpeningStockBatchService::class)->create(
                    $product,
                    (int) $product->stock,
                    (float) $product->cost_price,
                    (float) $product->sell_price
                );
            }

            return $product;
        });
    }

    public function update(Model $model, array $data): Model
    {
        return DB::transaction(function () use ($model, $data) {
            $hasVariants = array_key_exists('variants', $data);
            $variants = $data['variants'] ?? [];
            $imeis = $data['imeis'] ?? null;

            unset($data['variants'], $data['imeis']);

            $data = $this->normalizeProductData($data);

            // Chặn đổi loại sản phẩm (thường <-> IMEI, có / không biến thể) khi còn tồn kho, tránh lệch tồn.
            $this->guardProductTypeChange($model, $data, $hasVariants, $variants);

            $data['slug'] = $this->generateUniqueSlug($data['name'], $model->id);

            if (isset($data['image'])) {
                if ($model->image) {
                    Storage::disk('public')->delete($model->image);
                }

                $data['image'] = $data['image']->store('products', 'public');
            }

            $product = $this->repository->update($model, $data);

            if ($hasVariants) {
                $this->syncVariants($product, $variants, true);
            }

            $this->syncProductImeis($product, $imeis);

            return $product;
        });
    }

    public function trash($perPage = 10)
    {
        return $this->repository->trash($perPage);
    }

    public function restore(int $id): bool
    {
        return $this->repository->restore($id);
    }

    private function normalizeProductData(array $data): array
    {
        $data['manage_stock_by_serial'] = filter_var(
            $data['manage_stock_by_serial'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if (($data['product_type'] ?? null) === 'imei') {
            $data['manage_stock_by_serial'] = true;
        }

        if ($data['manage_stock_by_serial']) {
            $data['product_type'] = 'imei';
        } else {
            $data['product_type'] = $data['product_type'] ?? 'normal';
        }

        $data['has_unit_conversion'] = filter_var(
            $data['has_unit_conversion'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($data['manage_stock_by_serial'] || ! $data['has_unit_conversion']) {
            $data['has_unit_conversion'] = false;
            $data['conversion_unit_id'] = null;
            $data['conversion_factor'] = 1;

            return $data;
        }

        $data['conversion_factor'] = max(
            2,
            (int) ($data['conversion_factor'] ?? 1)
        );

        return $data;
    }

    private function syncVariants(Product $product, array $variants, bool $pruneMissing = false): void
    {
        if (empty($variants)) {
            if ($pruneMissing) {
                $this->pruneMissingVariants($product, collect());
            }

            return;
        }

        //  Lấy attributes từ FE (chỉ có 1 nhóm)
        $attributes = $variants[0]['attributes'] ?? [];

        //  Sync master trước (giữ nguyên logic bạn đang có)
        $attributes = $this->syncAttributeMasters($product, $attributes);

        //  Tạo tất cả combination
        $combinations = $this->generateCombinations($attributes);

        $seen = [];
        $desiredKeys = collect();
        $existingVariants = $product->variants()
            ->get()
            ->keyBy(fn (ProductVariant $variant) => $this->variantAttributeKey($variant->attributes ?? []));

        foreach ($combinations as $combo) {

            // format attributes
            $formattedAttributes = collect($combo)->map(function ($value, $name) {
                return [
                    'name' => $name,
                    'value' => $value
                ];
            })->values()->all();

            // tạo key unique theo attributes
            $key = $this->variantAttributeKey($formattedAttributes);

            if (isset($seen[$key])) {
                continue; // ❌ skip duplicate
            }

            $seen[$key] = true;
            $desiredKeys->push($key);

            $override = $variants[0]['overrides'][$key] ?? [];
            $costPrice = $override['cost_price'] ?? $variants[0]['cost_price'] ?? 0;
            $sellPrice = $override['sell_price'] ?? $variants[0]['sell_price'] ?? 0;
            $requestedSku = trim((string) ($override['sku'] ?? ''));

            if ($existingVariants->has($key)) {
                $existingVariant = $existingVariants->get($key);
                $existingVariant->update([
                    'attributes' => $formattedAttributes,
                    'sku' => $this->uniqueVariantSku(
                        $requestedSku ?: $existingVariant->sku,
                        $existingVariant->id
                    ),
                    'cost_price' => $costPrice,
                    'sell_price' => $sellPrice,
                    'is_active' => true,
                ]);

                continue;
            }

            // generate SKU
            $sku = $this->uniqueVariantSku(
                $requestedSku ?: $this->generateSku($product, [
                    'attributes' => $formattedAttributes
                ])
            );

            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $sku,
                'attributes' => $formattedAttributes,
                'cost_price' => $costPrice,
                'sell_price' => $sellPrice,
                'stock' => 0,
                'is_active' => true,
            ]);
        }

        if ($pruneMissing) {
            $this->pruneMissingVariants($product, $desiredKeys);
        }
    }

    /**
     * Đổi loại sản phẩm làm tồn kho lệch giữa các nơi lưu (product.stock, lô tồn, variant.stock, bảng IMEI),
     * vì mỗi loại quản lý tồn theo một cách khác nhau. Chỉ cho đổi khi chưa có tồn ở "nơi cũ".
     */
    private function guardProductTypeChange(Product $product, array $data, bool $hasVariants, array $variants): void
    {
        $errors = [];
        $wasSerial = (bool) $product->manage_stock_by_serial;
        $willSerial = (bool) ($data['manage_stock_by_serial'] ?? false);

        if ($wasSerial !== $willSerial) {
            if ($wasSerial && $this->inStockImeiQuery($product)->exists()) {
                $errors['manage_stock_by_serial'] = 'Không thể bỏ quản lý IMEI/Serial khi sản phẩm còn thiết bị đang tồn kho. '
                    . 'Hãy bán hết hoặc xử lý các IMEI còn tồn trước; nếu cần loại khác, hãy tạo sản phẩm mới.';
            }

            if (! $wasSerial && $this->holdsAnyStock($product)) {
                $errors['manage_stock_by_serial'] = 'Không thể chuyển sang sản phẩm quản lý IMEI/Serial khi sản phẩm còn tồn kho hoặc còn lô hàng. '
                    . 'Hãy đưa tồn kho về 0 (bán hết / kiểm kho) trước; nếu cần loại khác, hãy tạo sản phẩm mới.';
            }
        }

        // Thêm biến thể vào sản phẩm đang không có biến thể: tồn chung sẽ bị "kẹt" ở cấp sản phẩm, biến thể bán không được.
        if ($hasVariants && ! empty($variants) && ! $product->variants()->where('is_active', true)->exists()) {
            $hasProductLevelStock = (int) $product->stock !== 0
                || ProductStockBatch::query()->where('product_id', $product->id)->whereNull('variant_id')->where('remaining_quantity', '>', 0)->exists()
                || $this->inStockImeiQuery($product)->whereNull('variant_id')->exists();

            if ($hasProductLevelStock) {
                $errors['variants'] = 'Không thể thêm biến thể khi sản phẩm còn tồn kho chung (chưa thuộc biến thể nào). '
                    . 'Hãy đưa tồn về 0 trước, hoặc tạo sản phẩm mới có biến thể.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function inStockImeiQuery(Product $product)
    {
        return ProductImei::query()
            ->where('product_id', $product->id)
            ->where('status', ProductImei::STATUS_IN_STOCK);
    }

    private function holdsAnyStock(Product $product): bool
    {
        return (int) $product->stock !== 0
            || $product->variants()->where('stock', '!=', 0)->exists()
            || ProductStockBatch::query()->where('product_id', $product->id)->where('remaining_quantity', '>', 0)->exists()
            || $this->inStockImeiQuery($product)->exists();
    }

    private function variantHoldsStock(ProductVariant $variant): bool
    {
        return (int) $variant->stock !== 0
            || ProductStockBatch::query()->where('variant_id', $variant->id)->where('remaining_quantity', '>', 0)->exists()
            || ProductImei::query()->where('variant_id', $variant->id)->where('status', ProductImei::STATUS_IN_STOCK)->exists();
    }

    private function pruneMissingVariants(Product $product, $desiredKeys): void
    {
        $product->variants()
            ->get()
            ->each(function (ProductVariant $variant) use ($desiredKeys) {
                $key = $this->variantAttributeKey($variant->attributes ?? []);

                if ($desiredKeys->contains($key)) {
                    return;
                }

                // Biến thể đang hoạt động mà còn tồn thì không được bỏ: tồn sẽ biến mất khỏi bán hàng nhưng vẫn nằm trong tổng kho.
                if ($variant->is_active && $this->variantHoldsStock($variant)) {
                    $label = collect($variant->attributes ?? [])
                        ->map(fn ($attribute) => is_array($attribute) ? ($attribute['value'] ?? '') : $attribute)
                        ->filter()
                        ->implode(' / ') ?: ($variant->sku ?: 'đã chọn');

                    throw ValidationException::withMessages([
                        'variants' => "Biến thể “{$label}” còn tồn kho nên không thể bỏ khỏi danh sách. Hãy bán hết hoặc đưa tồn về 0 trước.",
                    ]);
                }

                if ($this->variantHasTransactions($variant)) {
                    $variant->update([
                        'is_active' => false,
                    ]);

                    return;
                }

                $variant->delete();
            });
    }

    private function variantHasTransactions(ProductVariant $variant): bool
    {
        return DB::table('stock_import_items')
            ->where('variant_id', $variant->id)
            ->exists()
            || DB::table('sale_items')
                ->where('variant_id', $variant->id)
                ->exists()
            || DB::table('product_imeis')
                ->where('variant_id', $variant->id)
                ->exists();
    }

    private function variantAttributeKey(array $attributes): string
    {
        return collect($attributes)
            ->map(function ($attribute, $name) {
                if (is_array($attribute)) {
                    $name = $attribute['name'] ?? $attribute['key'] ?? $name;
                    $value = $attribute['value'] ?? $attribute['label'] ?? '';
                } else {
                    $value = $attribute;
                }

                return mb_strtolower((string) $name) . '=' . mb_strtolower((string) $value);
            })
            ->sort()
            ->join('|');
    }

    private function uniqueVariantSku(string $baseSku, ?int $ignoreVariantId = null): string
    {
        $sku = $baseSku;
        $i = 1;

        while (ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreVariantId, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
            ->exists()) {
            $sku = $baseSku . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $i++;
        }

        return $sku;
    }

    private function syncAttributeMasters(Product $product, array $attributes): array
    {
        foreach ($attributes as &$attribute) {
            if (empty($attribute['name'])) {
                continue;
            }

            $categoryAttribute = CategoryAttribute::firstOrCreate([
                'category_id' => $product->category_id,
                'name' => $attribute['name'],
            ]);

            $values = $attribute['value'] ?? [];

            if (!is_array($values)) {
                $values = [$values];
            }

            $values = collect($values)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique(fn ($value) => mb_strtolower($value))
                ->values()
                ->all();

            foreach ($values as $value) {
                CategoryAttributeValue::firstOrCreate([
                    'attribute_id' => $categoryAttribute->id,
                    'value' => $value,
                ]);
            }

            $attribute['id'] = $categoryAttribute->id;
            $attribute['value'] = $values;
        }

        unset($attribute);

        return $attributes;
    }

    private function syncProductImeis(Product $product, ?string $rawImeis, ?ProductVariant $variant = null): void
    {
        if (!$product->manage_stock_by_serial || !$rawImeis) {
            return;
        }

        $imeis = preg_split('/\r\n|\r|\n/', trim($rawImeis));

        $addedCount = 0;

        foreach ($imeis as $imei) {
            $imei = trim($imei);

            if (!$imei || ProductImei::where('imei', $imei)->exists()) {
                continue;
            }

            ProductImei::create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'imei' => $imei,
            ]);

            $addedCount++;
        }

        if ($addedCount > 0) {
            app(ImeiStockSynchronizer::class)->sync($product);
        }
    }

    /**
     * Mã ngắn từ tên: nhiều từ thì lấy chữ cái đầu mỗi từ, một từ thì lấy các chữ cái đầu của từ đó
     * (tối đa $maxLength ký tự, bỏ dấu). Vd. "Điện thoại" => DT, "Máy tính bảng" => MTB, "Samsung" => SAM.
     */
    private function makeCodeFromWords(?string $text, int $maxLength = 3): string
    {
        $words = array_values(array_filter(explode('-', Str::slug((string) $text))));

        if ($words === []) {
            return '';
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, $maxLength));
        }

        return strtoupper(substr(implode('', array_map(fn ($word) => $word[0], $words)), 0, $maxLength));
    }

    /** Tiền tố SKU theo danh mục và thương hiệu, vd. "DT-SAM"; không có cả hai thì dùng "SP". */
    private function skuPrefix(?int $categoryId, ?int $brandId): string
    {
        $category = $categoryId ? \App\Models\Category::query()->whereKey($categoryId)->value('name') : null;
        $brand = $brandId ? \App\Models\Brand::query()->whereKey($brandId)->value('name') : null;

        $parts = array_filter([
            $this->makeCodeFromWords($category),
            $this->makeCodeFromWords($brand),
        ]);

        return $parts === [] ? 'SP' : implode('-', $parts);
    }

    /**
     * SKU sản phẩm dạng TIỀN_TỐ-SỐ_THỨ_TỰ, vd. DT-SAM-0001, DT-SAM-0002...
     * Số thứ tự tăng dần theo từng tiền tố và tính cả sản phẩm đã xóa (cột SKU là duy nhất kể cả khi đã xóa mềm).
     */
    public function nextProductSku(?int $categoryId, ?int $brandId): string
    {
        $prefix = $this->skuPrefix($categoryId, $brandId);
        $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';

        $max = Product::withTrashed()
            ->where('sku', 'like', $prefix . '-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match($pattern, (string) $sku, $matches) ? (int) $matches[1] : 0)
            ->max() ?? 0;

        $next = $max + 1;

        do {
            $sku = $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $taken = Product::withTrashed()->where('sku', $sku)->exists();
            $next++;
        } while ($taken);

        return $sku;
    }

    /** Gợi ý SKU hiển thị trên form; số thật được cấp lại ngay lúc lưu để tránh trùng khi nhiều người tạo cùng lúc. */
    public function previewNextSku(?int $categoryId, ?int $brandId): string
    {
        if (! $categoryId && ! $brandId) {
            return '';
        }

        return $this->nextProductSku($categoryId, $brandId);
    }

    /**
     * SKU biến thể = SKU sản phẩm + mã các giá trị thuộc tính, vd. DT-SAM-0001-DEN-128GB,
     * để biến thể luôn gắn với đúng sản phẩm của nó (trước đây dùng chung tiền tố danh mục/thương hiệu nên dễ trùng).
     */
    private function generateSku($product, $variant = null)
    {
        if (! $variant) {
            return $this->nextProductSku(
                $product->category_id ? (int) $product->category_id : null,
                $product->brand_id ? (int) $product->brand_id : null
            );
        }

        $base = $product->sku ?: $this->nextProductSku(
            $product->category_id ? (int) $product->category_id : null,
            $product->brand_id ? (int) $product->brand_id : null
        );

        $parts = [];

        foreach ($variant['attributes'] ?? [] as $attribute) {
            $value = $attribute['value'] ?? null;

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            $code = strtoupper(substr(Str::slug((string) $value, ''), 0, 5));

            if ($code !== '') {
                $parts[] = $code;
            }
        }

        return $base . ($parts ? '-' . implode('-', $parts) : '');
    }

    private function generateUniqueSlug($name, ?int $ignoreId = null)
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $original . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
