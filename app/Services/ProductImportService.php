<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\Unit;
use App\Services\Product\ProductService;
use App\Services\Stock\OpeningStockBatchService;
use App\Support\ProductImportColumns;
use App\Support\ProductImportOptions;
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

    /** Ghi chú cho người dùng sau khi đọc file (vd. file không có dòng tiêu đề). */
    public ?string $notice = null;

    public function __construct(
        private readonly OpeningStockBatchService $openingStock,
        private readonly ProductService $productService,
    ) {
    }

    /**
     * Kiểm tra file theo các lựa chọn của người dùng, trả về từng dòng kèm hành động sẽ làm:
     * create (thêm mới) | update (cập nhật sản phẩm có sẵn) | skip (bỏ qua) | error (lỗi).
     *
     * @param array<int, mixed> $images UploadedFile hoặc ['name' => ..., 'path' => ...]
     * @param array<int, int|string> $skipRows Số dòng (theo file Excel) người dùng chọn bỏ qua
     */
    public function preview(array $rows, array $images = [], ?ProductImportOptions $options = null, array $skipRows = []): array
    {
        return $this->readRows($rows, $images, $options ?? new ProductImportOptions(), $skipRows)['rows'];
    }

    /**
     * @param array<int, mixed> $images UploadedFile hoặc ['name' => ..., 'path' => ...]
     * @param array<int, int|string> $skipRows
     */
    public function handle(array $rows, array $images = [], ?ProductImportOptions $options = null, array $skipRows = []): array
    {
        $options ??= new ProductImportOptions();
        $parsed = $this->readRows($rows, $images, $options, $skipRows);

        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $storedImages = [];
        $currentRow = null;

        $errorRows = array_values(array_filter($parsed['rows'], fn (array $row) => $row['action'] === 'error'));

        // Chế độ "dừng nếu có lỗi": không nhập gì cả khi còn dòng lỗi.
        if ($options->onError === 'abort' && $errorRows !== []) {
            return [
                'success' => false,
                'aborted' => true,
                'count' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'message' => 'Có ' . count($errorRows) . ' dòng lỗi nên chưa nhập sản phẩm nào (bạn đã chọn dừng khi có lỗi).',
                'errors' => array_map(fn (array $row) => $this->errorPayload($row, $row['status']), $errorRows),
                'error_count' => count($errorRows),
            ];
        }

        DB::beginTransaction();

        try {
            foreach ($parsed['rows'] as $row) {
                if ($row['action'] === 'error') {
                    $errors[] = $this->errorPayload($row, $row['status']);
                    continue;
                }

                if ($row['action'] === 'skip') {
                    $skipped++;
                    continue;
                }

                $currentRow = $row;

                $category = $this->findOrCreateCategory($row['category']);
                $brand = $this->findOrCreateBrand($row['brand'], $category);
                $unit = $this->findOrCreateUnit($row['unit']);
                $cost = $row['cost_price'] !== '' ? (float) $row['cost_price'] : 0.0;
                $sell = (float) $row['sell_price'];
                $imageSource = $parsed['image_sources'][$row['row']] ?? null;

                if ($row['action'] === 'update') {
                    $product = Product::withTrashed()->find($row['existing_id']);

                    if (! $product) {
                        $errors[] = $this->errorPayload($row, 'Không tìm thấy sản phẩm để cập nhật (có thể vừa bị xóa vĩnh viễn)');
                        continue;
                    }

                    if ($product->trashed()) {
                        $product->restore();
                    }

                    $imagePath = $this->storeImage($imageSource, $product->sku);

                    if ($imagePath !== null) {
                        $storedImages[] = $imagePath;
                    }

                    $this->applyUpdate($product, $row, $category, $brand, $unit, $cost, $sell, $imagePath);
                    $updated++;

                    continue;
                }

                $isImei = $row['type'] === 'imei';
                $stock = $isImei ? 0 : (int) ($row['stock'] === '' ? 0 : $row['stock']);

                // SKU đã được kiểm tra trùng (hoặc tự tạo) ở bước đọc file; nếu vẫn trùng (người khác vừa tạo) thì DB báo lỗi và hoàn tác.
                $sku = $row['sku'];

                $imagePath = $this->storeImage($imageSource, $sku);

                if ($imagePath !== null) {
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

                $created++;
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $created + $updated,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
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
                'count' => 0, // đã hoàn tác toàn bộ: không có sản phẩm nào được thêm/cập nhật
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
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

    /**
     * Cập nhật sản phẩm có sẵn từ một dòng file. Chỉ ghi đè ô có giá trị; KHÔNG đổi tồn kho,
     * loại sản phẩm và cờ IMEI (đổi những thứ này làm lệch tồn kho/lô hàng).
     */
    private function applyUpdate(Product $product, array $row, ?Category $category, ?Brand $brand, ?Unit $unit, float $cost, float $sell, ?string $imagePath): void
    {
        $priceChanged = (float) $product->sell_price !== $sell;

        $attributes = [
            'name' => $row['name'],
            'category_id' => $category?->id,
            'sell_price' => $sell,
        ];

        if ($row['model'] !== '') {
            $attributes['model'] = $row['model'];
        }

        if ($brand) {
            $attributes['brand_id'] = $brand->id;
        }

        if ($unit) {
            $attributes['unit_id'] = $unit->id;
        }

        if ($row['cost_price'] !== '') {
            $attributes['cost_price'] = $cost;
        }

        if ($row['description'] !== '') {
            $attributes['description'] = $row['description'];
        }

        if ($row['active_given']) {
            $attributes['is_active'] = $row['active'] === '1';
        }

        if ($imagePath !== null) {
            $attributes['image'] = $imagePath;
        }

        $product->fill($attributes);
        $product->setRelation('category', $category);

        if ($brand) {
            $product->setRelation('brand', $brand);
        }

        $product->save();

        // Giống cập nhật giá hàng loạt: giá bán mới áp dụng cho các lô còn tồn.
        if ($priceChanged) {
            ProductStockBatch::query()
                ->where('product_id', $product->id)
                ->whereNull('variant_id')
                ->where('remaining_quantity', '>', 0)
                ->update(['sell_price' => $sell]);
        }
    }

    /* ------------------------------------------------------------------
     | Đọc & kiểm tra dữ liệu
     * ------------------------------------------------------------------ */

    private function readRows(array $rows, array $images, ProductImportOptions $options, array $skipRows): array
    {
        $this->notice = null;
        $header = ProductImportColumns::detectHeader($rows);

        // Không có dòng tiêu đề (dữ liệu bắt đầu từ dòng 1): đọc theo thứ tự cột của file mẫu và báo rõ cho người dùng.
        if ($header === null) {
            $header = ProductImportColumns::headerlessMap($rows);

            if ($header !== null) {
                $this->notice = 'File không có dòng tiêu đề nên dòng 1 được đọc như dữ liệu, theo thứ tự cột của file mẫu. Hãy kiểm tra cột trong bảng bên dưới; nếu sai, hãy thêm dòng tiêu đề (dùng file mẫu).';
            }
        }

        if ($header === null) {
            throw new ProductImportException('Không tìm thấy dòng tiêu đề. Vui lòng dùng file mẫu (cần có cột "Tên sản phẩm" và ít nhất một cột như "Danh mục", "Giá bán" hoặc "SKU").');
        }

        [$headerIndex, $headerMap] = $header;
        $missing = ProductImportColumns::missingRequired($headerMap);

        if ($missing !== []) {
            throw new ProductImportException('File thiếu cột bắt buộc: ' . implode(', ', $missing) . '. Vui lòng dùng file mẫu.');
        }

        $skipLookup = array_fill_keys(array_map('intval', $skipRows), true);
        $records = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerIndex) {
                continue;
            }

            $row = array_values((array) $row);

            if (! array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== '')) {
                continue;
            }

            $records[] = $this->rowData($row, $headerMap, $index + 1, $options);
        }

        if (count($records) > self::MAX_ROWS) {
            throw new ProductImportException('Mỗi lần chỉ nhập tối đa ' . number_format(self::MAX_ROWS, 0, ',', '.') . ' sản phẩm. File này có ' . number_format(count($records), 0, ',', '.') . ' dòng, vui lòng chia nhỏ file.');
        }

        $this->loadReferenceData();

        $imageIndex = $this->indexImages($this->normalizeImages($images));
        $existingSku = $this->existingProducts('sku', array_column($records, 'sku'));

        // Mọi SKU người dùng tự điền trong file: SKU tự tạo phải tránh các số này.
        $explicitSku = [];

        foreach ($records as $record) {
            if ($record['sku'] !== '') {
                $explicitSku[$this->dupKey($record['sku'])] = true;
            }
        }

        $result = [];
        $imageSources = [];
        $seenSku = [];
        $skuCounters = [];
        $announced = ['category' => [], 'brand' => [], 'unit' => []];

        foreach ($records as $data) {
            $errors = $data['parse_errors'];
            $warnings = $data['parse_warnings'];
            unset($data['parse_errors'], $data['parse_warnings']);

            // Người dùng chọn bỏ qua dòng này: không tham gia kiểm tra trùng hay cấp SKU.
            if (isset($skipLookup[$data['row']])) {
                $result[] = $this->finishRow($data, 'skip', 'Bỏ qua theo lựa chọn của bạn', $warnings, null, false);
                continue;
            }

            [$fieldErrors, $fieldWarnings] = $this->validateFields($data, $options);
            $errors = [...$errors, ...$fieldErrors];
            $warnings = [...$warnings, ...$fieldWarnings];

            // Danh mục / thương hiệu / đơn vị chưa có trong hệ thống.
            foreach ([
                'category' => ['danh mục', $this->categories, true],
                'brand' => ['thương hiệu', $this->brands, false],
                'unit' => ['đơn vị tính', $this->units, false],
            ] as $kind => [$label, $known, $required]) {
                $name = $data[$kind];
                $key = $this->dupKey($name);

                if ($name === '' || isset($known[$key])) {
                    continue;
                }

                if ($options->missingReference === 'create') {
                    if (! isset($announced[$kind][$key])) {
                        $announced[$kind][$key] = true;
                        $warnings[] = 'Sẽ tạo mới ' . $label . ' "' . $name . '"';
                    }

                    continue;
                }

                $message = ucfirst($label) . ' "' . $name . '" chưa có trong hệ thống';

                if ($required || $options->optionalField === 'error') {
                    $errors[] = $message;
                } else {
                    $warnings[] = $message . ' → đã bỏ qua giá trị này';
                    $data[$kind] = '';
                }
            }

            if ($data['image_name'] !== '') {
                $match = $this->matchImage($data['image_name'], $imageIndex);

                if ($match['error'] === null) {
                    $imageSources[$data['row']] = $match['source'];
                } elseif ($options->optionalField === 'ignore') {
                    $warnings[] = $match['error'] . ' → nhập không kèm ảnh';
                    $data['image_name'] = '';
                } else {
                    $errors[] = $match['error'];
                }
            }

            // SKU: trùng trong file, trùng hệ thống, hoặc để trống (tự tạo).
            $action = 'create';
            $skipReason = null;
            $existingId = null;
            $needAuto = $data['sku'] === '';
            $skuAuto = $needAuto;

            if ($data['sku'] !== '') {
                $key = $this->dupKey($data['sku']);

                if (isset($seenSku[$key])) {
                    if ($options->duplicateInFile === 'error') {
                        $errors[] = 'SKU trong file bị lặp (trùng dòng ' . $seenSku[$key] . ')';
                    } elseif ($options->duplicateInFile === 'skip') {
                        $action = 'skip';
                        $skipReason = 'SKU lặp trong file (trùng dòng ' . $seenSku[$key] . '), bỏ qua dòng này';
                    } else {
                        $needAuto = true;
                        $skuAuto = true;
                        $warnings[] = 'SKU "' . $data['sku'] . '" lặp trong file (trùng dòng ' . $seenSku[$key] . '), đã tạo SKU mới';
                    }
                } else {
                    $seenSku[$key] = $data['row'];

                    if (isset($existingSku[$key])) {
                        $existing = $existingSku[$key];
                        $trashed = $existing['state'] === 'trashed';

                        if ($options->duplicateSku === 'error') {
                            $errors[] = $trashed
                                ? 'SKU đang nằm trong thùng rác (hãy khôi phục, xóa vĩnh viễn, hoặc chọn cách xử lý SKU trùng ở trên)'
                                : 'SKU đã tồn tại';
                        } elseif ($options->duplicateSku === 'skip') {
                            $action = 'skip';
                            $skipReason = $trashed ? 'SKU đang trong thùng rác, bỏ qua dòng này' : 'SKU đã tồn tại, bỏ qua dòng này';
                        } elseif ($options->duplicateSku === 'update') {
                            $action = 'update';
                            $existingId = $existing['id'];
                            $warnings[] = $trashed
                                ? 'Sản phẩm đang trong thùng rác: sẽ khôi phục và cập nhật'
                                : 'Sẽ cập nhật sản phẩm có sẵn (không đổi tồn kho)';

                            if ($data['stock'] !== '' && (float) $data['stock'] > 0) {
                                $warnings[] = 'Cột tồn kho được bỏ qua khi cập nhật';
                            }
                        } else {
                            $needAuto = true;
                            $skuAuto = true;
                            $warnings[] = 'SKU "' . $data['sku'] . '" đã tồn tại, đã tạo SKU mới';
                        }
                    }
                }
            }

            if ($needAuto && $errors === [] && $action === 'create') {
                $data['sku'] = $this->generateSku($data, $skuCounters, $explicitSku, $seenSku);
            }

            if ($errors !== []) {
                $result[] = $this->finishRow($data, 'error', implode('; ', $errors), $warnings, null, $skuAuto);
            } elseif ($action === 'skip') {
                $result[] = $this->finishRow($data, 'skip', (string) $skipReason, $warnings, null, $skuAuto);
            } else {
                $result[] = $this->finishRow($data, $action, $action === 'update' ? 'Cập nhật' : 'OK', $warnings, $existingId, $skuAuto, $action === 'update' && ($existingSku[$this->dupKey($data['sku'])]['state'] ?? '') === 'trashed');
            }
        }

        return [
            'rows' => $result,
            'image_sources' => $imageSources,
        ];
    }

    private function finishRow(array $data, string $action, string $status, array $warnings, ?int $existingId, bool $skuAuto, bool $existingTrashed = false): array
    {
        return [
            ...$data,
            'action' => $action,
            'status' => $status,
            'is_error' => $action === 'error',
            'is_skipped' => $action === 'skip',
            'warning' => implode('; ', $warnings),
            'sku_auto' => $skuAuto,
            'existing_id' => $existingId,
            'existing_trashed' => $existingTrashed,
        ];
    }

    /**
     * SKU tự tạo giống khi thêm thủ công: TIỀN_TỐ(danh mục)-(thương hiệu)-SỐ_THỨ_TỰ, vd. DT-SAM-0001.
     * Số thứ tự tiếp nối số lớn nhất đang có trong hệ thống (kể cả thùng rác) và không trùng SKU nào trong file.
     *
     * @param array<string, int> $counters
     * @param array<string, bool> $explicit
     * @param array<string, int> $seen
     */
    private function generateSku(array $data, array &$counters, array $explicit, array &$seen): string
    {
        $category = $this->categories[$this->dupKey($data['category'])] ?? null;
        $brand = $data['brand'] !== '' ? ($this->brands[$this->dupKey($data['brand'])] ?? null) : null;

        $prefix = ProductService::skuPrefixForNames(
            $category?->name ?? $data['category'],
            $data['brand'] !== '' ? ($brand?->name ?? $data['brand']) : null
        );

        $counters[$prefix] ??= $this->productService->maxSkuNumber($prefix);

        do {
            $sku = ProductService::formatSku($prefix, ++$counters[$prefix]);
            $key = $this->dupKey($sku);
        } while (isset($explicit[$key]) || isset($seen[$key]));

        $seen[$key] = (int) $data['row'];

        return $sku;
    }

    private function rowData(array $row, array $headerMap, int $rowNumber, ProductImportOptions $options): array
    {
        $errors = [];
        $warnings = [];

        // Giá trị sai ở cột không bắt buộc: bỏ qua giá trị (dùng mặc định) hoặc coi cả dòng là lỗi, theo lựa chọn.
        $optionalIssue = function (string $message) use (&$errors, &$warnings, $options): bool {
            if ($options->optionalField === 'ignore') {
                $warnings[] = $message . ' → đã bỏ qua giá trị này';

                return true;
            }

            $errors[] = $message;

            return false;
        };

        $typeText = ProductImportColumns::value($row, $headerMap, 'type');
        $imeiText = ProductImportColumns::value($row, $headerMap, 'manage_stock_by_serial');
        $activeText = ProductImportColumns::value($row, $headerMap, 'active');

        $type = $typeText === '' ? null : $this->parseType($typeText);
        $hasImei = $imeiText === '' ? null : $this->parseBool($imeiText);
        $active = $activeText === '' ? true : $this->parseBool($activeText);

        $typeInvalid = $typeText !== '' && $type === null;
        $imeiInvalid = $imeiText !== '' && $hasImei === null;
        $activeInvalid = $activeText !== '' && $active === null;

        if ($typeInvalid && $optionalIssue('Loại sản phẩm không hợp lệ ("' . $typeText . '"), chỉ nhận: normal, imei, service, combo')) {
            $typeInvalid = false; // dùng mặc định: normal
        }

        if ($imeiInvalid && $optionalIssue('Cột "Có IMEI" không hợp lệ ("' . $imeiText . '"), hãy nhập 1/0 hoặc Có/Không')) {
            $imeiInvalid = false;
            $hasImei = null;
        }

        if ($activeInvalid && $optionalIssue('Cột "Trạng thái" không hợp lệ ("' . $activeText . '"), hãy nhập 1/0 hoặc Hoạt động/Ngừng')) {
            $activeInvalid = false;
            $active = true;
            $activeText = '';
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
            'type' => $typeInvalid ? $typeText : $finalType,
            'manage_stock_by_serial' => $imeiInvalid ? $imeiText : ($isImei ? '1' : '0'),
            'active' => $activeInvalid ? $activeText : ($active === false ? '0' : '1'),
            'active_given' => $activeText !== '' && ! $activeInvalid,
            'image_name' => ProductImportColumns::value($row, $headerMap, 'image'),
            'description' => ProductImportColumns::value($row, $headerMap, 'description'),
            'parse_errors' => $errors,
            'parse_warnings' => $warnings,
        ];
    }

    /**
     * Kiểm tra từng ô (không phụ thuộc các dòng khác). Cột bắt buộc sai luôn là lỗi dòng;
     * cột không bắt buộc sai thì bỏ qua giá trị hoặc báo lỗi tùy lựa chọn.
     *
     * @return array{0: array<int, string>, 1: array<int, string>} [lỗi, cảnh báo]
     */
    private function validateFields(array &$row, ProductImportOptions $options): array
    {
        $errors = [];
        $warnings = [];

        $optional = function (string $message, string $field, string $resetTo = '') use (&$errors, &$warnings, &$row, $options): void {
            if ($options->optionalField === 'ignore') {
                $warnings[] = $message . ' → đã bỏ qua giá trị này';
                $row[$field] = $resetTo;

                return;
            }

            $errors[] = $message;
        };

        if ($row['name'] === '') {
            $errors[] = 'Thiếu tên sản phẩm';
        } elseif (mb_strlen($row['name']) > self::MAX_NAME) {
            $errors[] = 'Tên sản phẩm quá dài (tối đa ' . self::MAX_NAME . ' ký tự)';
        }

        // SKU để trống là hợp lệ (hệ thống tự tạo); SKU đã điền thì phải đúng độ dài.
        if ($row['sku'] !== '' && mb_strlen($row['sku']) > self::MAX_CODE) {
            $errors[] = 'SKU quá dài (tối đa ' . self::MAX_CODE . ' ký tự)';
        }

        if (mb_strlen($row['model']) > self::MAX_CODE) {
            $optional('Model quá dài (tối đa ' . self::MAX_CODE . ' ký tự)', 'model');
        }

        if ($row['category'] === '') {
            $errors[] = 'Thiếu danh mục';
        } elseif (mb_strlen($row['category']) > self::MAX_NAME) {
            $errors[] = 'Danh mục quá dài (tối đa ' . self::MAX_NAME . ' ký tự)';
        }

        foreach (['brand' => 'Thương hiệu', 'unit' => 'Đơn vị tính'] as $field => $label) {
            if (mb_strlen($row[$field]) > self::MAX_NAME) {
                $optional($label . ' quá dài (tối đa ' . self::MAX_NAME . ' ký tự)', $field);
            }
        }

        if (mb_strlen($row['image_name']) > self::MAX_NAME) {
            $optional('Tên ảnh quá dài', 'image_name');
        }

        $errors = [...$errors, ...$this->validateMoney($row['sell_price'], 'Giá bán', true)];

        foreach ($this->validateMoney($row['cost_price'], 'Giá nhập', false) as $message) {
            $optional($message, 'cost_price');
        }

        if ($row['stock'] !== '') {
            $stockError = null;

            if (! is_numeric($row['stock'])) {
                $stockError = 'Tồn kho không hợp lệ';
            } elseif ((float) $row['stock'] < 0) {
                $stockError = 'Tồn kho không được âm';
            } elseif (floor((float) $row['stock']) !== (float) $row['stock']) {
                $stockError = 'Tồn kho phải là số nguyên';
            } elseif ((float) $row['stock'] > self::MAX_STOCK) {
                $stockError = 'Tồn kho quá lớn';
            } elseif ($row['type'] === 'imei' && (float) $row['stock'] > 0) {
                $stockError = 'Sản phẩm IMEI phải nhập tồn qua phiếu nhập hàng, không nhập tồn trực tiếp trong file sản phẩm';
            }

            if ($stockError !== null) {
                $optional($stockError, 'stock', $row['type'] === 'imei' ? '0' : '');
            }
        }

        return [$errors, $warnings];
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
     * @return array<string, array{state: 'active'|'trashed', id: int}>
     */
    private function existingProducts(string $column, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, fn ($value) => $value !== '')));
        $found = [];

        foreach (array_chunk($values, 500) as $chunk) {
            Product::withTrashed()
                ->whereIn($column, $chunk)
                ->get([$column, 'deleted_at', 'id'])
                ->each(function (Product $product) use ($column, &$found): void {
                    $key = $this->dupKey((string) $product->{$column});
                    $state = $product->deleted_at ? 'trashed' : 'active';

                    // Có cả bản đang dùng lẫn bản trong thùng rác: ưu tiên bản đang dùng.
                    if (! isset($found[$key]) || $state === 'active') {
                        $found[$key] = ['state' => $state, 'id' => (int) $product->id];
                    }
                });
        }

        return $found;
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

        $extension = $source['extension'] ?? strtolower(pathinfo($source['path'], PATHINFO_EXTENSION));
        $filename = (Str::slug($sku) ?: 'sp') . '_' . Str::lower(Str::random(10)) . '.' . $extension;

        // Ảnh đã có trên máy chủ: sao chép thành file riêng để xóa/đổi ảnh của sản phẩm này không làm mất ảnh sản phẩm khác.
        if ($source['type'] === 'stored') {
            $target = 'products/' . $filename;

            return Storage::disk('public')->copy($source['path'], $target) ? $target : null;
        }

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
