<?php

namespace App\Exports;

use App\Models\Product;
use App\Support\ProductImportColumns;
use Maatwebsite\Excel\Concerns\{
    FromQuery,
    ShouldAutoSize,
    WithEvents,
    WithHeadings,
    WithMapping,
    WithTitle,
};
use Maatwebsite\Excel\Events\AfterSheet;


class ProductsDataExport implements
    FromQuery,
    ShouldAutoSize,
    WithEvents,
    WithHeadings,
    WithMapping,
    WithTitle
{
    protected $export;

    public function __construct($export)
    {
        $this->export = $export;
    }

    public function query()
    {
        return Product::with(['category', 'brand', 'unit'])
            ->selectRaw('products.*, ROW_NUMBER() OVER (ORDER BY products.id) as row_num');
    }

    public function headings(): array
    {
        return ProductImportColumns::HEADINGS;
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
            $p->unit?->short_name ?: $p->unit?->name,
            $p->product_type,
            $p->manage_stock_by_serial ? 1 : 0,
            $p->is_active ? 1 : 0,
            $p->image,
            $p->description,
        ];
    }

    public function title(): string
    {
        return 'DS Sản phẩm';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet;

                /* ===== 1. STYLE HEADER ===== */
                $sheet->getStyle('A1:O1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12
                    ],
                    'alignment' => [
                        'horizontal' => 'center',
                        'vertical' => 'center'
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => 'thin'
                        ]
                    ]
                ]);

                /* ===== 2. TÔ ĐỎ CỘT BẮT BUỘC ===== */
                // B, C, E, G = Tên, SKU, Danh mục, Giá bán
                $sheet->getStyle('B1')->getFont()->getColor()->setARGB('FFFF0000');
                $sheet->getStyle('C1')->getFont()->getColor()->setARGB('FFFF0000');
                $sheet->getStyle('E1')->getFont()->getColor()->setARGB('FFFF0000');
                $sheet->getStyle('G1')->getFont()->getColor()->setARGB('FFFF0000');

                /* ===== 3. FREEZE HEADER ===== */
                $sheet->freezePane('A2');

                /* ===== 4. AUTO WIDTH ===== */
                foreach (range('A', 'O') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

            }
        ];
    }
}
