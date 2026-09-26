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
            $normalizedHeader[self::normalizeHeader($heading)] = $index;
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

    public static function value(array $row, array $headerMap, string $field, ?int $fallbackIndex = null): string
    {
        $index = $headerMap[$field] ?? $fallbackIndex;

        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
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
