<?php

namespace App\Services\Product;

use App\Models\CategoryAttribute;
use App\Models\CategoryAttributeValue;
use App\Models\Product;
use App\Models\ProductImei;
use App\Models\ProductVariant;
use App\Repositories\Product\ProductRepository;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        return DB::transaction(function () use ($data) {
            $variants = $data['variants'] ?? [];
            $imeis = $data['imeis'] ?? null;

            unset($data['variants'], $data['imeis']);

            $data = $this->normalizeProductData($data);
            $data['slug'] = $this->generateUniqueSlug($data['name']);
            $data['barcode'] = $data['barcode'] ?? null;

            if (isset($data['image'])) {
                $data['image'] = $data['image']->store('products', 'public');
            }

            if (empty($data['sku'])) {
                $data['sku'] = 'TEMP-' . time();
            }

            $product = $this->repository->create($data);

            if (str_starts_with($product->sku, 'TEMP-')) {
                $product->sku = $this->generateSku($product);
                $product->save();
            }

            $this->syncVariants($product, $variants);
            $this->syncProductImeis($product, $imeis);

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

            $costPrice = $variants[0]['cost_price'] ?? 0;
            $sellPrice = $variants[0]['sell_price'] ?? 0;

            if ($existingVariants->has($key)) {
                $existingVariants->get($key)->update([
                    'attributes' => $formattedAttributes,
                    'cost_price' => $costPrice,
                    'sell_price' => $sellPrice,
                    'is_active' => true,
                ]);

                continue;
            }

            // generate SKU
            $sku = $this->uniqueVariantSku(
                $this->generateSku($product, [
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

    private function pruneMissingVariants(Product $product, $desiredKeys): void
    {
        $product->variants()
            ->get()
            ->each(function (ProductVariant $variant) use ($desiredKeys) {
                $key = $this->variantAttributeKey($variant->attributes ?? []);

                if ($desiredKeys->contains($key)) {
                    return;
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

    private function uniqueVariantSku(string $baseSku): string
    {
        $sku = $baseSku;
        $i = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
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
        }
    }

    private function makeCodeFromWords($text)
    {
        $text = Str::slug($text);
        $words = explode('-', $text);

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 3));
        }

        if (count($words) === 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 2));
        }

        return strtoupper(implode('', array_map(fn ($word) => substr($word, 0, 1), $words)));
    }


    public function previewNextSku(?int $categoryId, ?int $brandId): string
    {
        if (!$categoryId || !$brandId) {
            return '';
        }

        $category = \App\Models\Category::find($categoryId)?->name ?? 'GEN';
        $brand = \App\Models\Brand::find($brandId)?->name ?? 'GEN';

        $base = $this->makeCodeFromWords($category)
            . $this->makeCodeFromWords($brand);

        // Nếu base chưa tồn tại thì vẫn giữ logic SKU đầu tiên
        if (!Product::where('sku', $base)->exists()) {
            return $base;
        }

        $i = 1;

        while (
            Product::where(
                'sku',
                $base . '-' . str_pad($i, 3, '0', STR_PAD_LEFT)
            )->exists()
        ) {
            $i++;
        }

        return $base . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
    }


    private function generateSku($product, $variant = null)
    {
        $category = \App\Models\Category::find($product->category_id)?->name ?? 'GEN';
        $brand = \App\Models\Brand::find($product->brand_id)?->name ?? 'GEN';

        $base = $this->makeCodeFromWords($category) . $this->makeCodeFromWords($brand);

        if (!$variant) {
            $sku = $base;
            $i = 1;

            while (Product::where('sku', $sku)->exists()) {
                $sku = $base . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $i++;
            }

            return $sku;
        }

        $parts = [];

        foreach ($variant['attributes'] ?? [] as $attribute) {
            if (empty($attribute['value'])) {
                continue;
            }

            $value = is_array($attribute['value'])
                ? $attribute['value'][0]
                : $attribute['value'];

            $parts[] = strtoupper(substr(Str::slug($value), 0, 4));
        }

        $suffix = implode('-', $parts);

        return $base . ($suffix ? "-{$suffix}" : '');
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
