<?php

namespace App\Exports;

use App\Support\ProductImportColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ImportErrorExport implements FromArray, WithHeadings, WithEvents
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
                $e['cost_price'] ?? '',
                $e['stock'] ?? '',
                $e['unit'] ?? '',
                $e['type'] ?? '',
                $e['manage_stock_by_serial'] ?? '',
                $e['active'] ?? '',
                $e['image_name'] ?? '',
                $e['description'] ?? '',
                $e['error'] ?? '',
            ];
        })->toArray();
    }

    public function headings(): array
    {
        return ProductImportColumns::headingsWithError();
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
                $sheet->getStyle('A1:P1')->applyFromArray([
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
                foreach (range('A', 'P') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

            }
        ];
    }
}
