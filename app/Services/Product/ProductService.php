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
                $product->variants()->delete();
                $this->syncVariants($product, $variants);
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

    private function syncVariants(Product $product, array $variants): void
    {
        if (empty($variants)) {
            return;
        }

        //  Lấy attributes từ FE (chỉ có 1 nhóm)
        $attributes = $variants[0]['attributes'] ?? [];

        //  Sync master trước (giữ nguyên logic bạn đang có)
        $attributes = $this->syncAttributeMasters($product, $attributes);

        //  Tạo tất cả combination
        $combinations = $this->generateCombinations($attributes);

        $seen = [];

        foreach ($combinations as $combo) {

            // format attributes
            $formattedAttributes = collect($combo)->map(function ($value, $name) {
                return [
                    'name' => $name,
                    'value' => $value
                ];
            })->values()->all();

            // tạo key unique theo attributes
            $key = collect($formattedAttributes)
                ->map(fn($a) => strtolower($a['name'].'='.$a['value']))
                ->sort()
                ->join('|');

            if (isset($seen[$key])) {
                continue; // ❌ skip duplicate
            }

            $seen[$key] = true;

            // generate SKU
            $sku = $this->generateSku($product, [
                'attributes' => $formattedAttributes
            ]);

            // tránh trùng SKU DB (phòng thêm)
            if (ProductVariant::where('sku', $sku)->exists()) {
                continue;
            }

            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $sku,
                'attributes' => $formattedAttributes,
                'cost_price' => $variants[0]['cost_price'] ?? 0,
                'sell_price' => $variants[0]['sell_price'] ?? 0,
                'stock' => 0,
            ]);
        }
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
