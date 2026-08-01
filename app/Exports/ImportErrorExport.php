<?php

namespace App\Exports;

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
            'Giá nhập',
            'Tồn kho',
            'Loại sản phẩm',
            'Trạng thái',
            'Ảnh',
            'Lỗi'
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
                $sheet->getStyle('A1:L1')->applyFromArray([
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
                // B, C, G = Tên, SKU, Giá bán
                $sheet->getStyle('B1')->getFont()->getColor()->setARGB('FFFF0000');
                $sheet->getStyle('C1')->getFont()->getColor()->setARGB('FFFF0000');
                $sheet->getStyle('G1')->getFont()->getColor()->setARGB('FFFF0000');

                /* ===== 3. FREEZE HEADER ===== */
                $sheet->freezePane('A2');

                /* ===== 4. AUTO WIDTH ===== */
                foreach (range('A', 'L') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

            }
        ];
    }
}