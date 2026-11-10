<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Stock\OpeningStockBatchService;
use App\Support\ProductImportColumns;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductImportService
{
    /** Số sản phẩm tối đa mỗi lần nhập (tránh quá thời gian xử lý của PHP). */
    public const MAX_ROWS = 5000;

    private const TYPES = ['normal', 'imei', 'service', 'combo'];

    /** Giới hạn theo cấu trúc bảng products. */
    private const MAX_DECIMAL = 9999999999999.99; // decimal(15,2)
    private const MAX_STOCK = 4294967295;         // unsigned int
    private const MAX_NAME = 255;
    private const MAX_CODE = 100;                 // SKU, model (giống form thêm sản phẩm)

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const TYPE_ALIASES = [
        'normal' => 'normal', 'thuong' => 'normal', 'binhthuong' => 'normal', 'hangthuong' => 'normal', 'hanghoa' => 'normal',
        'imei' => 'imei', 'serial' => 'imei', 'quanlyimei' => 'imei',
        'service' => 'service', 'dichvu' => 'service',
        'combo' => 'combo',
    ];

    private const TRUE_VALUES = ['1', 'true', 'yes', 'y', 'co', 'bat', 'active', 'x', 'dung', 'on', 'hoatdong', 'kinhdoanh', 'dangkinhdoanh', 'dangdung'];
    private const FALSE_VALUES = ['0', 'false', 'no', 'n', 'khong', 'tat', 'inactive', 'off', 'ngung', 'ngungkinhdoanh', 'tamtat', 'tamngung'];

    /** @var array<string, Category> */
    private array $categories = [];
    /** @var array<string, Brand> */
    private array $brands = [];
    /** @var array<string, Unit> */
    private array $units = [];

    public function __construct(private readonly OpeningStockBatchService $openingStock)
    {
    }

    /**
     * @param array<int, mixed> $images UploadedFile hoặc ['name' => ..., 'path' => ...]
     */
    public function preview(array $rows, array $images = []): array
    {
        return $this->readRows($rows, $images, false)['rows'];
    }

    /**
     * @param array<int, mixed> $images UploadedFile hoặc ['name' => ..., 'path' => ...]
     */
    public function handle(array $rows, bool $allowDuplicate = false, array $images = []): array
    {
        $parsed = $this->readRows($rows, $images, $allowDuplicate);
        $errors = [];
        $success = 0;
        $storedImages = [];
        $currentRow = null;

        DB::beginTransaction();

        try {
            foreach ($parsed['rows'] as $row) {
                if ($row['is_error']) {
                    $errors[] = $this->errorPayload($row, $row['status']);
                    continue;
                }

                $currentRow = $row;

                $category = $this->findOrCreateCategory($row['category']);
                $brand = $this->findOrCreateBrand($row['brand'], $category);
                $unit = $this->findOrCreateUnit($row['unit']);
                $isImei = $row['type'] === 'imei';
                $stock = $isImei ? 0 : (int) ($row['stock'] === '' ? 0 : $row['stock']);
                $cost = $row['cost_price'] !== '' ? (float) $row['cost_price'] : 0.0;
                $sell = (float) $row['sell_price'];

                $skuExists = $this->skuTaken($row['sku']);
                $sku = $skuExists ? $this->uniqueSku($row['sku']) : $row['sku'];

                $imagePath = $this->storeImage($parsed['image_sources'][$row['row']] ?? null, $sku);

                if ($imagePath !== null && ($parsed['image_sources'][$row['row']]['type'] ?? null) === 'upload') {
                    $storedImages[] = $imagePath;
                }

                $product = new Product([
                    'name' => $row['name'],
                    'sku' => $sku,
                    'slug' => $this->uniqueSlug($row['name']),
                    'model' => $row['model'] !== '' ? $row['model'] : null,
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'unit_id' => $unit?->id,
                    'sell_price' => $sell,
                    'cost_price' => $cost,
                    'stock' => $stock,
                    'product_type' => $row['type'],
                    'manage_stock_by_serial' => $isImei,
                    'is_active' => $row['active'] === '1',
                    'description' => $row['description'] !== '' ? $row['description'] : null,
                    'image' => $imagePath,
                ]);

                // Model tự dựng search_text từ danh mục/thương hiệu: gán sẵn quan hệ để khỏi truy vấn lại.
                $product->setRelation('category', $category);
                $product->setRelation('brand', $brand);
                $product->save();

                // Tồn nhập trực tiếp phải có lô tồn đầu kỳ thì bán hàng mới trừ kho được.
                if ($stock > 0) {
                    $this->openingStock->create($product, $stock, $cost, $sell);
                }

                $success++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $success,
                'errors' => $errors,
                'error_count' => count($errors),
            ];
        } catch (Throwable $e) {
            DB::rollBack();

            foreach ($storedImages as $path) {
                Storage::disk('public')->delete($path);
            }

            report($e);

            return [
                'success' => false,
                'count' => 0, // đã hoàn tác toàn bộ: không có sản phẩm nào được thêm
                'errors' => [[
                    'row' => $currentRow['row'] ?? '',
                    'name' => $currentRow['name'] ?? '',
                    'sku' => $currentRow['sku'] ?? '',
                    'error' => 'Không lưu được, toàn bộ file chưa được nhập. ' . $this->friendlyDatabaseError($e),
                ]],
                'error_count' => 1,
            ];
        }
    }

    /* ------------------------------------------------------------------
     | Đọc & kiểm tra dữ liệu
     * ------------------------------------------------------------------ */

    private function readRows(array $rows, array $images, bool $allowDuplicate): array
    {
        $header = ProductImportColumns::detectHeader($rows);

        if ($header === null) {
            throw new ProductImportException('Không tìm thấy dòng tiêu đề. Vui lòng dùng file mẫu (cần ít nhất cột "Tên sản phẩm" và "SKU").');
        }

        [$headerIndex, $headerMap] = $header;
        $missing = ProductImportColumns::missingRequired($headerMap);

        if ($missing !== []) {
            throw new ProductImportException('File thiếu cột bắt buộc: ' . implode(', ', $missing) . '. Vui lòng dùng file mẫu.');
        }

        $records = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }

            $row = array_values((array) $row);

            if (! array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== '')) {
                continue;
            }

            $records[] = $this->rowData($row, $headerMap, $index + 1);
        }

        if (count($records) > self::MAX_ROWS) {
            throw new ProductImportException('Mỗi lần chỉ nhập tối đa ' . number_format(self::MAX_ROWS, 0, ',', '.') . ' sản phẩm. File này có ' . number_format(count($records), 0, ',', '.') . ' dòng, vui lòng chia nhỏ file.');
        }

        $this->loadReferenceData();

        $imageIndex = $this->indexImages($this->normalizeImages($images));
        $existingSku = $this->existingProducts('sku', array_column($records, 'sku'));

        $result = [];
        $imageSources = [];
        $seenSku = [];
        $announced = ['category' => [], 'brand' => [], 'unit' => []];

        foreach ($records as $data) {
            $errors = $data['parse_errors'];
            $warnings = $data['parse_warnings'];
            unset($data['parse_errors'], $data['parse_warnings']);

            $errors = [...$errors, ...$this->validateFields($data)];

            // Trùng trong file: dòng đầu tiên được giữ, các dòng sau báo lỗi (xem trước và nhập dùng chung luật này).
            if ($data['sku'] !== '') {
                $key = $this->dupKey($data['sku']);

                if (isset($seenSku[$key])) {
                    $errors[] = 'SKU trong file bị lặp (trùng dòng ' . $seenSku[$key] . ')';
                } else {
                    $seenSku[$key] = $data['row'];
                }

                if (! $allowDuplicate && isset($existingSku[$key])) {
                    $errors[] = $existingSku[$key] === 'trashed'
                        ? 'SKU đang nằm trong thùng rác (hãy khôi phục hoặc xóa vĩnh viễn sản phẩm đó trước)'
                        : 'SKU đã tồn tại';
                }
            }

            if ($data['image_name'] !== '') {
                $match = $this->matchImage($data['image_name'], $imageIndex);

                if ($match['error'] !== null) {
                    $errors[] = $match['error'];
                } else {
                    $imageSources[$data['row']] = $match['source'];
                }
            }

            foreach ([
                'category' => [$data['category'], $this->categories, 'danh mục'],
                'brand' => [$data['brand'], $this->brands, 'thương hiệu'],
                'unit' => [$data['unit'], $this->units, 'đơn vị tính'],
            ] as $kind => [$name, $known, $label]) {
                $key = $this->dupKey($name);

                if ($name !== '' && ! isset($known[$key]) && ! isset($announced[$kind][$key])) {
                    $announced[$kind][$key] = true;
                    $warnings[] = 'Sẽ tạo mới ' . $label . ' "' . $name . '"';
                }
            }

            $result[] = [
                ...$data,
                'status' => $errors === [] ? 'OK' : implode('; ', $errors),
                'is_error' => $errors !== [],
                'warning' => implode('; ', $warnings),
            ];
        }

        return [
            'rows' => $result,
            'image_sources' => $imageSources,
        ];
    }

    private function rowData(array $row, array $headerMap, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];

        $typeText = ProductImportColumns::value($row, $headerMap, 'type');
        $imeiText = ProductImportColumns::value($row, $headerMap, 'manage_stock_by_serial');
        $activeText = ProductImportColumns::value($row, $headerMap, 'active');

        $type = $typeText === '' ? null : $this->parseType($typeText);
        $hasImei = $imeiText === '' ? null : $this->parseBool($imeiText);
        $active = $activeText === '' ? true : $this->parseBool($activeText);

        if ($typeText !== '' && $type === null) {
            $errors[] = 'Loại sản phẩm không hợp lệ ("' . $typeText . '"), chỉ nhận: normal, imei, service, combo';
        }

        if ($imeiText !== '' && $hasImei === null) {
            $errors[] = 'Cột "Có IMEI" không hợp lệ ("' . $imeiText . '"), hãy nhập 1/0 hoặc Có/Không';
        }

        if ($activeText !== '' && $active === null) {
            $errors[] = 'Cột "Trạng thái" không hợp lệ ("' . $activeText . '"), hãy nhập 1/0 hoặc Hoạt động/Ngừng';
        }

        $isImei = $hasImei === true || $type === 'imei';

        if ($hasImei === false && $type === 'imei') {
            $warnings[] = 'Loại sản phẩm là imei nên vẫn quản lý theo IMEI';
        }

        $finalType = $isImei ? 'imei' : ($type ?? 'normal');

        return [
            'row' => $rowNumber,
            'name' => $this->cleanText(ProductImportColumns::value($row, $headerMap, 'name')),
            'sku' => ProductImportColumns::value($row, $headerMap, 'sku'),
            'model' => ProductImportColumns::value($row, $headerMap, 'model'),
            'category' => $this->cleanText(ProductImportColumns::value($row, $headerMap, 'category')),
            'brand' => $this->cleanText(ProductImportColumns::value($row, $headerMap, 'brand')),
            'sell_price' => ProductImportColumns::normalizeNumber(ProductImportColumns::raw($row, $headerMap, 'sell_price')),
            'cost_price' => ProductImportColumns::normalizeNumber(ProductImportColumns::raw($row, $headerMap, 'cost_price')),
            'stock' => ProductImportColumns::normalizeNumber(ProductImportColumns::raw($row, $headerMap, 'stock')),
            'unit' => $this->cleanText(ProductImportColumns::value($row, $headerMap, 'unit')),
            'type' => $typeText !== '' && $type === null ? $typeText : $finalType,
            'manage_stock_by_serial' => $imeiText !== '' && $hasImei === null ? $imeiText : ($isImei ? '1' : '0'),
            'active' => $activeText !== '' && $active === null ? $activeText : ($active === false ? '0' : '1'),
            'image_name' => ProductImportColumns::value($row, $headerMap, 'image'),
            'description' => ProductImportColumns::value($row, $headerMap, 'description'),
            'parse_errors' => $errors,
            'parse_warnings' => $warnings,
        ];
    }

    /** Kiểm tra từng ô (không phụ thuộc các dòng khác). @return array<int, string> */
    private function validateFields(array $row): array
    {
        $errors = [];

        if ($row['name'] === '') {
            $errors[] = 'Thiếu tên sản phẩm';
        } elseif (mb_strlen($row['name']) > self::MAX_NAME) {
            $errors[] = 'Tên sản phẩm quá dài (tối đa ' . self::MAX_NAME . ' ký tự)';
        }

        if ($row['sku'] === '') {
            $errors[] = 'Thiếu SKU';
        } elseif (mb_strlen($row['sku']) > self::MAX_CODE) {
            $errors[] = 'SKU quá dài (tối đa ' . self::MAX_CODE . ' ký tự)';
        }

        if (mb_strlen($row['model']) > self::MAX_CODE) {
            $errors[] = 'Model quá dài (tối đa ' . self::MAX_CODE . ' ký tự)';
        }

        if ($row['category'] === '') {
            $errors[] = 'Thiếu danh mục';
        }

        foreach (['category' => 'Danh mục', 'brand' => 'Thương hiệu', 'unit' => 'Đơn vị tính'] as $field => $label) {
            if (mb_strlen($row[$field]) > self::MAX_NAME) {
                $errors[] = $label . ' quá dài (tối đa ' . self::MAX_NAME . ' ký tự)';
            }
        }

        if (mb_strlen($row['image_name']) > self::MAX_NAME) {
            $errors[] = 'Tên ảnh quá dài';
        }

        $errors = [...$errors, ...$this->validateMoney($row['sell_price'], 'Giá bán', true)];
        $errors = [...$errors, ...$this->validateMoney($row['cost_price'], 'Giá nhập', false)];

        if ($row['stock'] !== '') {
            if (! is_numeric($row['stock'])) {
                $errors[] = 'Tồn kho không hợp lệ';
            } elseif ((float) $row['stock'] < 0) {
                $errors[] = 'Tồn kho không được âm';
            } elseif (floor((float) $row['stock']) !== (float) $row['stock']) {
                $errors[] = 'Tồn kho phải là số nguyên';
            } elseif ((float) $row['stock'] > self::MAX_STOCK) {
                $errors[] = 'Tồn kho quá lớn';
            } elseif ($row['type'] === 'imei' && (float) $row['stock'] > 0) {
                $errors[] = 'Sản phẩm IMEI phải nhập tồn qua phiếu nhập hàng, không nhập tồn trực tiếp trong file sản phẩm';
            }
        }

        return $errors;
    }

    /** @return array<int, string> */
    private function validateMoney(string $value, string $label, bool $required): array
    {
        if ($value === '') {
            return $required ? [$label . ' không hợp lệ'] : [];
        }

        if (! is_numeric($value)) {
            return [$label . ' không hợp lệ'];
        }

        if ((float) $value < 0) {
            return [$label . ' không được âm'];
        }

        if ((float) $value > self::MAX_DECIMAL) {
            return [$label . ' quá lớn'];
        }

        return [];
    }

    private function parseType(string $value): ?string
    {
        return self::TYPE_ALIASES[$this->token($value)] ?? null;
    }

    private function parseBool(string $value): ?bool
    {
        $token = $this->token($value);

        if (in_array($token, self::TRUE_VALUES, true)) {
            return true;
        }

        return in_array($token, self::FALSE_VALUES, true) ? false : null;
    }

    /** Bỏ dấu, chữ thường, chỉ giữ chữ-số: "Hoạt động" → "hoatdong". */
    private function token(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** Khóa so sánh giống collation không phân biệt hoa-thường/dấu của MySQL. */
    private function dupKey(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /* ------------------------------------------------------------------
     | Tra cứu dữ liệu có sẵn (tính cả sản phẩm đã xóa mềm)
     * ------------------------------------------------------------------ */

    /**
     * @param array<int, string> $values
     * @return array<string, 'active'|'trashed'>
     */
    private function existingProducts(string $column, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn ($value) => $value !== '')));
        $found = [];

        foreach (array_chunk($values, 500) as $chunk) {
            Product::withTrashed()
                ->whereIn($column, $chunk)
                ->get([$column, 'deleted_at'])
                ->each(function (Product $product) use ($column, &$found): void {
                    $key = $this->dupKey((string) $product->{$column});
                    $state = $product->deleted_at ? 'trashed' : 'active';

                    // Có cả bản đang dùng lẫn bản trong thùng rác: ưu tiên báo "đã tồn tại".
                    if (! isset($found[$key]) || $state === 'active') {
                        $found[$key] = $state;
                    }
                });
        }

        return $found;
    }

    private function skuTaken(string $sku): bool
    {
        return Product::withTrashed()->where('sku', $sku)->exists();
    }

    private function loadReferenceData(): void
    {
        $this->categories = [];
        $this->brands = [];
        $this->units = [];

        foreach (Category::query()->get() as $category) {
            $this->categories[$this->dupKey((string) $category->name)] ??= $category;
        }

        foreach (Brand::query()->get() as $brand) {
            $this->brands[$this->dupKey((string) $brand->name)] ??= $brand;
        }

        foreach (Unit::query()->get() as $unit) {
            $this->units[$this->dupKey((string) $unit->name)] ??= $unit;

            if ($unit->short_name) {
                $this->units[$this->dupKey((string) $unit->short_name)] ??= $unit;
            }
        }
    }

    private function findOrCreateCategory(string $name): ?Category
    {
        if ($name === '') {
            return null;
        }

        $key = $this->dupKey($name);

        return $this->categories[$key] ??= Category::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, Category::class),
            'is_active' => true,
        ]);
    }

    private function findOrCreateBrand(string $name, ?Category $category): ?Brand
    {
        if ($name === '') {
            return null;
        }

        $key = $this->dupKey($name);
        $brand = $this->brands[$key] ?? null;

        if ($brand) {
            // Thương hiệu dùng chung: gặp thêm một danh mục mới thì liên kết thêm, không báo lỗi.
            if ($category) {
                $brand->categories()->syncWithoutDetaching([$category->id]);
            }

            return $brand;
        }

        $brand = Brand::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, Brand::class),
            'is_active' => true,
        ]);

        if ($category) {
            $brand->categories()->attach($category->id);
        }

        return $this->brands[$key] = $brand;
    }

    private function findOrCreateUnit(string $name): ?Unit
    {
        if ($name === '') {
            return null;
        }

        $key = $this->dupKey($name);

        return $this->units[$key] ??= Unit::query()->create([
            'name' => $name,
            'short_name' => $name,
            'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------------
     | Ảnh
     * ------------------------------------------------------------------ */

    /**
     * @param array<int, mixed> $images
     * @return array<int, array{name: string, path: string}>
     */
    private function normalizeImages(array $images): array
    {
        $list = [];

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $path = $image->getRealPath();
                $name = $image->getClientOriginalName();
            } elseif (is_array($image) && isset($image['name'], $image['path'])) {
                $path = $image['path'];
                $name = $image['name'];
            } else {
                continue;
            }

            if ($path && is_file($path)) {
                $list[] = ['name' => (string) $name, 'path' => (string) $path];
            }
        }

        return $list;
    }

    /**
     * Gom ảnh theo tên đã chuẩn hóa (bỏ đuôi, dấu, ký tự đặc biệt).
     *
     * @param array<int, array{name: string, path: string}> $images
     * @return array<string, array<int, array{name: string, path: string}>>
     */
    private function indexImages(array $images): array
    {
        $index = [];

        foreach ($images as $image) {
            $index[$this->normalizeFileName($image['name'])][] = $image;
        }

        return $index;
    }

    /**
     * Chỉ khớp chính xác theo tên (không khớp "chứa nhau" để tránh gán nhầm ảnh).
     *
     * @return array{source: ?array, error: ?string}
     */
    private function matchImage(string $imageName, array $imageIndex): array
    {
        $key = $this->normalizeFileName($imageName);

        if ($key !== '' && isset($imageIndex[$key])) {
            $candidates = $imageIndex[$key];

            if (count($candidates) > 1) {
                return ['source' => null, 'error' => 'Có ' . count($candidates) . ' ảnh trùng tên "' . $imageName . '", hãy đổi tên cho khác nhau'];
            }

            $extension = strtolower(pathinfo($candidates[0]['name'], PATHINFO_EXTENSION));

            if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
                return ['source' => null, 'error' => 'Ảnh "' . $imageName . '" không đúng định dạng (jpg, png, webp, gif)'];
            }

            return ['source' => ['type' => 'upload', 'path' => $candidates[0]['path'], 'extension' => $extension], 'error' => null];
        }

        // File xuất ra ghi tên ảnh đã lưu (vd. sku_ab12cd34.jpg): dùng lại ảnh đó nếu còn trên máy chủ.
        $basename = basename(str_replace('\\', '/', $imageName));

        if (preg_match('/^[A-Za-z0-9._-]+$/', $basename) && Storage::disk('public')->exists('products/' . $basename)) {
            return ['source' => ['type' => 'stored', 'path' => 'products/' . $basename], 'error' => null];
        }

        return ['source' => null, 'error' => 'Không tìm thấy file ảnh "' . $imageName . '" trong số ảnh đã tải lên'];
    }

    private function storeImage(?array $source, string $sku): ?string
    {
        if ($source === null) {
            return null;
        }

        if ($source['type'] === 'stored') {
            return $source['path'];
        }

        $filename = (Str::slug($sku) ?: 'sp') . '_' . Str::lower(Str::random(10)) . '.' . $source['extension'];
        $stored = Storage::disk('public')->putFileAs('products', new File($source['path']), $filename);

        return $stored ?: null;
    }

    private function normalizeFileName(string $value): string
    {
        $name = pathinfo(str_replace('\\', '/', $value), PATHINFO_FILENAME);

        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name))) ?? '';
    }

    /* ------------------------------------------------------------------
     | Tiện ích
     * ------------------------------------------------------------------ */

    private function uniqueSku(string $sku): string
    {
        $base = $sku;
        $index = 1;

        do {
            $sku = $base . '-' . now()->format('His') . '-' . $index++;
        } while ($this->skuTaken($sku));

        return $sku;
    }

    private function uniqueSlug(string $name, string $modelClass = Product::class): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $index = 1;

        // Slug có unique index nên phải tính cả bản ghi đã xóa mềm.
        $newQuery = fn () => in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        while ($newQuery()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $index++;
        }

        return $slug;
    }

    private function friendlyDatabaseError(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');

            if ($sqlState === '23000') {
                return 'Dữ liệu bị trùng (SKU hoặc đường dẫn) hoặc vi phạm ràng buộc của cơ sở dữ liệu.';
            }

            if (in_array($sqlState, ['22001', '22003', '22007'], true)) {
                return 'Có giá trị quá dài hoặc vượt giới hạn cho phép của cơ sở dữ liệu.';
            }

            return 'Lỗi cơ sở dữ liệu, vui lòng thử lại hoặc liên hệ quản trị viên.';
        }

        return Str::limit($e->getMessage(), 300);
    }

    private function errorPayload(array $row, string $message): array
    {
        return [
            ...$row,
            'error' => $message,
        ];
    }
}
