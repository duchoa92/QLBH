<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductsTemplateSheet implements WithHeadings, WithTitle
{
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
            'Giá vốn',
            'Tồn kho',
            'Loại sản phẩm',
            'Trạng thái',
            'Ảnh'
        ];
    }

    public function title(): string
    {
        return 'PRODUCTS';
    }
}