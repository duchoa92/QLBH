<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductImportColumns
{
    public const HEADINGS = [
        'STT',
        'Tên sản phẩm*',
        'SKU*',
        'Barcode',
        'Danh mục*',
        'Thương hiệu',
        'Giá bán*',
        'Giá nhập',
        'Tồn kho',
        'Đơn vị tính',
        'Loại sản phẩm',
        'Có IMEI',
        'Trạng thái',
        'Ảnh',
        'Mô tả',
    ];

    /** Cột bắt buộc phải có trong file (field => tên hiển thị). */
    public const REQUIRED = [
        'name' => 'Tên sản phẩm',
        'sku' => 'SKU',
        'category' => 'Danh mục',
        'sell_price' => 'Giá bán',
    ];

    private const ALIASES = [
        'name' => ['ten san pham', 'ten hang hoa', 'name', 'product name'],
        'sku' => ['sku', 'ma sku', 'ma san pham'],
        'barcode' => ['barcode', 'ma vach'],
        'category' => ['danh muc', 'category'],
        'brand' => ['thuong hieu', 'brand', 'hang'],
        'sell_price' => ['gia ban', 'sell price', 'price'],
        'cost_price' => ['gia nhap', 'gia von', 'cost price'],
        'stock' => ['ton kho', 'stock'],
        'unit' => ['don vi tinh', 'unit'],
        'type' => ['loai san pham', 'loai', 'product type'],
        'manage_stock_by_serial' => ['co imei', 'imei', 'quan ly imei', 'quan ly serial'],
        'active' => ['trang thai', 'kich hoat', 'is active', 'active'],
        'image' => ['anh', 'image', 'hinh anh'],
        'description' => ['mo ta', 'description'],
    ];

    public static function headingsWithError(): array
    {
        return [
            ...self::HEADINGS,
            'Lỗi',
        ];
    }

    public static function headerMap(array $header): array
    {
        $normalizedHeader = [];

        foreach ($header as $index => $heading) {
            $key = self::normalizeHeader($heading);

            // Cột trùng tên: lấy cột xuất hiện đầu tiên.
            if ($key !== '' && ! array_key_exists($key, $normalizedHeader)) {
                $normalizedHeader[$key] = $index;
            }
        }

        $map = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $key = self::normalizeHeader($alias);

                if (array_key_exists($key, $normalizedHeader)) {
                    $map[$field] = $normalizedHeader[$key];
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Tìm dòng tiêu đề trong 10 dòng đầu (cho phép có dòng trống/tiêu đề phía trên).
     *
     * @return array{0: int, 1: array<string, int>}|null [chỉ số dòng, bản đồ cột]
     */
    public static function detectHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $map = self::headerMap(array_values((array) $row));

            if (isset($map['name'], $map['sku'])) {
                return [(int) $index, $map];
            }
        }

        return null;
    }

    /** @return array<int, string> Tên hiển thị của các cột bắt buộc còn thiếu. */
    public static function missingRequired(array $headerMap): array
    {
        $missing = [];

        foreach (self::REQUIRED as $field => $label) {
            if (! isset($headerMap[$field])) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public static function raw(array $row, array $headerMap, string $field): mixed
    {
        $index = $headerMap[$field] ?? null;

        return $index === null ? null : ($row[$index] ?? null);
    }

    public static function value(array $row, array $headerMap, string $field): string
    {
        $value = self::raw($row, $headerMap, $field);

        if ($value === null) {
            return '';
        }

        if (is_float($value)) {
            return self::floatToString($value);
        }

        return trim((string) $value);
    }

    /** 6.5E+5 / 650000.0 → "650000"; 12.5 → "12.5" (không dùng ký hiệu khoa học). */
    public static function floatToString(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    /**
     * Chuẩn hóa số nhập kiểu Việt Nam. Ô số của Excel (int/float) giữ nguyên;
     * chỉ ô chữ mới được hiểu dấu chấm là phân cách hàng nghìn:
     *   "650.000" → 650000, "1.250.000,5" → 1250000.5, "650,000" → 650000, "5,5" → 5.5
     * Chuỗi không đọc được trả về nguyên văn để bước kiểm tra báo lỗi.
     */
    public static function normalizeNumber(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_int($raw) || is_float($raw)) {
            return is_float($raw) ? self::floatToString($raw) : (string) $raw;
        }

        $text = trim((string) $raw);
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $text) ?? $text;
        $text = preg_replace('/(vnđ|vnd|đ|₫)$/iu', '', $text) ?? $text;

        if ($text === '') {
            return '';
        }

        if (preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+(,\d+)?$/', $text)) {
            return str_replace(',', '.', str_replace('.', '', $text));
        }

        if (preg_match('/^-?[1-9]\d{0,2}(,\d{3})+(\.\d+)?$/', $text)) {
            return str_replace(',', '', $text);
        }

        if (preg_match('/^-?\d+,\d{1,2}$/', $text)) {
            return str_replace(',', '.', $text);
        }

        return $text;
    }

    public static function normalizeHeader(mixed $value): string
    {
        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            Str::lower(Str::ascii((string) $value))
        ) ?? '';
    }
}
