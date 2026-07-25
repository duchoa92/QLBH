<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportErrorExport implements FromArray, WithHeadings
{
    protected $errors;

    public function __construct($errors)
    {
        $this->errors = $errors;
    }

    public function array(): array
    {
        return collect($this->errors)->map(function ($e) {

            return [
                $e['name'] ?? '',
                $e['sku'] ?? '',
                $e['barcode'] ?? '',
                $e['category'] ?? '',
                $e['brand'] ?? '',
                $e['sell_price'] ?? '',
                $e['cost_price'] ?? '',
                $e['stock'] ?? '',
                $e['type'] ?? '',
                $e['active'] ?? '',
                $e['error'] ?? '',
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return [
            'Tên sản phẩm',
            'SKU',
            'Barcode',
            'Danh mục',
            'Thương hiệu',
            'Giá bán',
            'Giá vốn',
            'Tồn kho',
            'Loại',
            'Kích hoạt',
            'Lỗi'
        ];
    }
}