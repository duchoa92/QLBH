<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\ExportHistory;
use Maatwebsite\Excel\Concerns\{
    FromQuery,
    WithHeadings,
    WithMapping,
};

class ProductsDataExport implements 
    FromQuery,
    WithHeadings,
    WithMapping
{
    protected $export;

    public function __construct($export)
    {
        $this->export = $export;
    }

    public function query()
    {
        return Product::with(['category', 'brand'])
            ->selectRaw('products.*, ROW_NUMBER() OVER (ORDER BY products.id) as row_num');
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
            'Giá vốn',
            'Tồn kho',
            'Loại',
            'Kích hoạt',
            'Ảnh'
        ];
    }

    public function map($p): array
    {
        // 👉 mỗi dòng chạy qua đây → update progress

        static $count = 0;

        $count++;

        if ($count % 100 == 0) {
            $total = Product::count();

            $percent = intval(($count / $total) * 100);

            $this->export->update([
                'progress' => min($percent, 99)
            ]);
        }

        return [
            
            $p->row_num,
            $p->name,
            $p->sku,
            $p->barcode,
            $p->category?->name,
            $p->brand?->name,
            $p->sell_price,
            $p->cost_price,
            $p->stock,
            $p->product_type,
            $p->is_active ? 1 : 0,
            $p->image,
        ];
    }
}