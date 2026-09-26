<?php

namespace App\Exports;

use App\Support\ProductImportColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ProductsTemplateSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    public function array(): array
    {
        return [
            [
                1,
                'Camera Ezviz H8C',
                'CAM-EZVIZ-H8C',
                '',
                'Camera',
                'Ezviz',
                650000,
                520000,
                0,
                'Cái',
                'imei',
                1,
                1,
                'camera-h8c.jpg',
                'Có QR, service code hoặc thông tin thiết bị thì nhập khi nhập hàng theo IMEI.',
            ],
            [
                2,
                'Dây mạng Cat6',
                'DAY-CAT6',
                '',
                'Phụ kiện',
                '',
                5000,
                3000,
                100,
                'Mét',
                'normal',
                0,
                1,
                '',
                '',
            ],
        ];
    }

    public function headings(): array
    {
        return ProductImportColumns::HEADINGS;
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
