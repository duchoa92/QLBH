<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Support\ProductImportColumns;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{
    private const TYPES = ['normal', 'imei', 'service', 'combo'];

    public function preview(array $rows, array $images = []): array
    {
        return $this->readRows($rows, $images, true)['rows'];
    }

    public function handle(array $rows, bool $allowDuplicate = false, array $images = []): array
    {
        $parsed = $this->readRows($rows, $images);
        $errors = [];
        $success = 0;
        $skuList = [];
        $barcodeList = [];

        DB::beginTransaction();

        try {
            foreach ($parsed['rows'] as $row) {
                if ($row['is_error']) {
                    $errors[] = $this->errorPayload($row, $row['status']);
                    continue;
                }

                $skuKey = Str::lower($row['sku']);

                if (in_array($skuKey, $skuList, true)) {
                    $errors[] = $this->errorPayload($row, 'SKU trong file bị lặp');
                    continue;
                }

                $skuList[] = $skuKey;

                $barcodeKey = Str::lower($row['barcode']);

                if ($barcodeKey !== '' && in_array($barcodeKey, $barcodeList, true)) {
                    $errors[] = $this->errorPayload($row, 'Barcode trong file bị lặp');
                    continue;
                }

                if ($barcodeKey !== '') {
                    $barcodeList[] = $barcodeKey;
                }

                $exists = Product::query()
                    ->where('sku', $row['sku'])
                    ->exists();

                if ($exists && ! $allowDuplicate) {
                    $errors[] = $this->errorPayload($row, 'SKU đã tồn tại');
                    continue;
                }

                $category = $this->findOrCreateCategory($row['category']);
                $brand = $this->findOrCreateBrand($row['brand'], $category);
                $unit = $this->findOrCreateUnit($row['unit']);
                $type = $this->normalizeType($row['type'], $row['manage_stock_by_serial']);
                $sku = $exists ? $this->uniqueSku($row['sku']) : $row['sku'];
                $imagePath = $this->storeImage($row['image_name'], $parsed['image_files'], $sku);

                Product::query()->create([
                    'name' => $row['name'],
                    'sku' => $sku,
                    'slug' => $this->uniqueSlug($row['name']),
                    'barcode' => $row['barcode'] ?: null,
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'unit_id' => $unit?->id,
                    'sell_price' => (float) $row['sell_price'],
                    'cost_price' => $row['cost_price'] !== '' ? (float) $row['cost_price'] : 0,
                    'stock' => $type === 'imei' ? 0 : (int) ($row['stock'] ?: 0),
                    'product_type' => $type,
                    'manage_stock_by_serial' => $type === 'imei' || $this->toBool($row['manage_stock_by_serial']),
                    'is_active' => $this->toBool($row['active'], true),
                    'description' => $row['description'] ?: null,
                    'search_text' => trim($row['name'] . ' ' . $sku . ' ' . $row['barcode']),
                    'image' => $imagePath,
                ]);

                $success++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $success,
                'errors' => $errors,
                'error_count' => count($errors),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'count' => $success,
                'errors' => [[
                    'row' => '',
                    'name' => '',
                    'sku' => '',
                    'error' => $e->getMessage(),
                ]],
                'error_count' => 1,
            ];
        }
    }

    private function readRows(array $rows, array $images = [], bool $preview = false): array
    {
        $imageFiles = $this->imageFilesByName($images);
        $header = $rows[0] ?? [];
        $headerMap = ProductImportColumns::headerMap($header);
        $result = [];
        $seenSku = [];
        $seenBarcode = [];

        foreach ($rows as $index => $row) {
            if ($index === 0 || empty(array_filter((array) $row))) {
                continue;
            }

            $row = array_values((array) $row);
            $data = $this->rowData($row, $headerMap, $index + 1);
            $status = $this->validateRow($data, $imageFiles, $preview, $seenSku, $seenBarcode);

            if ($preview && $data['sku'] !== '') {
                $seenSku[] = Str::lower($data['sku']);
            }

            if ($preview && $data['barcode'] !== '') {
                $seenBarcode[] = Str::lower($data['barcode']);
            }

            $result[] = [
                ...$data,
                'status' => $status ?: 'OK',
                'is_error' => (bool) $status,
            ];
        }

        return [
            'rows' => $result,
            'image_files' => $imageFiles,
        ];
    }

    private function rowData(array $row, array $headerMap, int $rowNumber): array
    {
        $type = ProductImportColumns::value($row, $headerMap, 'type', 10);
        $hasImei = ProductImportColumns::value($row, $headerMap, 'manage_stock_by_serial', 11);
        $active = ProductImportColumns::value($row, $headerMap, 'active', 12);

        if ($hasImei === '' && in_array(Str::lower($type), self::TYPES, true)) {
            $hasImei = $type === 'imei' ? '1' : '0';
        }

        return [
            'row' => $rowNumber,
            'name' => ProductImportColumns::value($row, $headerMap, 'name', 1),
            'sku' => ProductImportColumns::value($row, $headerMap, 'sku', 2),
            'barcode' => ProductImportColumns::value($row, $headerMap, 'barcode', 3),
            'category' => ProductImportColumns::value($row, $headerMap, 'category', 4),
            'brand' => ProductImportColumns::value($row, $headerMap, 'brand', 5),
            'sell_price' => ProductImportColumns::value($row, $headerMap, 'sell_price', 6),
            'cost_price' => ProductImportColumns::value($row, $headerMap, 'cost_price', 7),
            'stock' => ProductImportColumns::value($row, $headerMap, 'stock', 8),
            'unit' => ProductImportColumns::value($row, $headerMap, 'unit', 9),
            'type' => $type ?: 'normal',
            'manage_stock_by_serial' => $hasImei,
            'active' => $active === '' ? '1' : $active,
            'image_name' => ProductImportColumns::value($row, $headerMap, 'image', 13),
            'description' => ProductImportColumns::value($row, $headerMap, 'description', 14),
        ];
    }

    private function validateRow(array $row, array $imageFiles, bool $preview, array $seenSku, array $seenBarcode): ?string
    {
        if ($row['name'] === '') {
            return 'Thiếu tên sản phẩm';
        }

        if ($row['sku'] === '') {
            return 'Thiếu SKU';
        }

        if ($row['category'] === '') {
            return 'Thiếu danh mục';
        }

        if ($row['sell_price'] === '' || ! is_numeric($row['sell_price'])) {
            return 'Giá bán không hợp lệ';
        }

        if ($row['cost_price'] !== '' && ! is_numeric($row['cost_price'])) {
            return 'Giá nhập không hợp lệ';
        }

        if ($row['stock'] !== '' && (! is_numeric($row['stock']) || (int) $row['stock'] < 0)) {
            return 'Tồn kho không hợp lệ';
        }

        if (! in_array($this->normalizeType($row['type'], $row['manage_stock_by_serial']), self::TYPES, true)) {
            return 'Loại sản phẩm không hợp lệ';
        }

        if ($this->normalizeType($row['type'], $row['manage_stock_by_serial']) === 'imei' && (int) ($row['stock'] ?: 0) > 0) {
            return 'Sản phẩm IMEI phải nhập tồn qua phiếu nhập hàng, không nhập tồn trực tiếp trong file sản phẩm';
        }

        if ($row['brand'] !== '') {
            $category = $this->findCategory($row['category']);
            $brand = $this->findBrand($row['brand']);

            if ($brand && $category && $brand->category_id && (int) $brand->category_id !== (int) $category->id) {
                return 'Thương hiệu không thuộc danh mục đã nhập';
            }
        }

        if ($preview && in_array(Str::lower($row['sku']), $seenSku, true)) {
            return 'SKU trong file bị lặp';
        }

        if ($preview && $row['barcode'] !== '' && in_array(Str::lower($row['barcode']), $seenBarcode, true)) {
            return 'Barcode trong file bị lặp';
        }

        if ($preview && Product::query()->where('sku', $row['sku'])->exists()) {
            return 'SKU đã tồn tại';
        }

        if ($row['barcode'] !== '' && Product::query()->where('barcode', $row['barcode'])->exists()) {
            return 'Barcode đã tồn tại';
        }

        if ($row['image_name'] !== '' && ! $this->matchingImage($row['image_name'], $imageFiles)) {
            return 'Không tìm thấy file ảnh upload';
        }

        return null;
    }

    private function findOrCreateCategory(string $name): ?Category
    {
        if ($name === '') {
            return null;
        }

        return $this->findCategory($name) ?: Category::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, Category::class),
            'is_active' => true,
        ]);
    }

    private function findCategory(string $name): ?Category
    {
        return Category::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();
    }

    private function findOrCreateBrand(string $name, ?Category $category): ?Brand
    {
        if ($name === '') {
            return null;
        }

        $brand = $this->findBrand($name);

        if ($brand) {
            if (! $brand->category_id && $category) {
                $brand->update(['category_id' => $category->id]);
            }

            return $brand;
        }

        return Brand::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, Brand::class),
            'category_id' => $category?->id,
            'is_active' => true,
        ]);
    }

    private function findBrand(string $name): ?Brand
    {
        return Brand::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();
    }

    private function findOrCreateUnit(string $name): ?Unit
    {
        if ($name === '') {
            return null;
        }

        $unit = Unit::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orWhereRaw('LOWER(short_name) = ?', [Str::lower($name)])
            ->first();

        return $unit ?: Unit::query()->create([
            'name' => $name,
            'short_name' => $name,
            'is_active' => true,
        ]);
    }

    private function imageFilesByName(array $images): array
    {
        $files = [];

        foreach ($images as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $files[$this->normalizeFileName($file->getClientOriginalName())] = $file;
        }

        return $files;
    }

    private function matchingImage(string $imageName, array $imageFiles): ?UploadedFile
    {
        $imageKey = $this->normalizeFileName($imageName);

        foreach ($imageFiles as $key => $file) {
            if ($key === $imageKey || str_contains($key, $imageKey) || str_contains($imageKey, $key)) {
                return $file;
            }
        }

        return null;
    }

    private function storeImage(string $imageName, array $imageFiles, string $sku): ?string
    {
        if ($imageName === '') {
            return null;
        }

        $file = $this->matchingImage($imageName, $imageFiles);

        if (! $file) {
            return null;
        }

        return $file->storeAs(
            'products',
            Str::slug($sku) . '_' . time() . '.' . $file->getClientOriginalExtension(),
            'public'
        );
    }

    private function normalizeFileName(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(pathinfo($value, PATHINFO_FILENAME)))) ?? '';
    }

    private function normalizeType(string $type, string $hasImei): string
    {
        $type = Str::lower(trim($type));

        if ($this->toBool($hasImei) || $type === 'imei') {
            return 'imei';
        }

        return in_array($type, self::TYPES, true) ? $type : 'normal';
    }

    private function toBool(string|int|bool|null $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'co', 'có', 'bat', 'bật', 'active'], true);
    }

    private function uniqueSku(string $sku): string
    {
        $base = $sku;
        $index = 1;

        do {
            $sku = $base . '-' . now()->format('His') . '-' . $index++;
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name, string $modelClass = Product::class): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $index = 1;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $index++;
        }

        return $slug;
    }

    private function errorPayload(array $row, string $message): array
    {
        return [
            ...$row,
            'error' => $message,
        ];
    }
}
