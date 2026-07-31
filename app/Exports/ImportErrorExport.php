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
                $e['row'] ?? '',
                $e['name'] ?? '',
                $e['sku'] ?? '',
                $e['barcode'] ?? '',
                $e['category'] ?? '',
                $e['brand'] ?? '',
                $e['sell_price'] ?? '',
                $e['type'] ?? '',
                $e['active'] ?? '',
                $e['image_name'] ?? '',
                $e['error'] ?? '',
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return [
            'STT',
            'Tên sản phẩm',
            'SKU',
            'Barcode',
            'Danh mục',
            'Thương hiệu',
            'Giá bán',
            'Loại sản phẩm',
            'Trạng thái',
            'Ảnh',
            'Lỗi'
        ];
    }
}