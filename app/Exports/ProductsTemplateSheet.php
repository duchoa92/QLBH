<?php

namespace App\Exports;

use App\Support\ProductImportColumns;
use App\Support\SafeSpreadsheetValues;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class ProductsTemplateSheet extends DefaultValueBinder implements FromArray, WithHeadings, WithTitle, WithEvents, WithCustomValueBinder
{
    use SafeSpreadsheetValues;

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
                '',
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

                /* ===== 5. SKU / BARCODE LÀ VĂN BẢN (giữ số 0 đầu) ===== */
                $sheet->getStyle('C2:D5000')->getNumberFormat()->setFormatCode('@');

                /* ===== 6. DANH SÁCH CHỌN CHO CỘT LOẠI / CÓ IMEI / TRẠNG THÁI ===== */
                $lists = [
                    'K' => '"normal,imei,service,combo"',
                    'L' => '"0,1"',
                    'M' => '"0,1"',
                ];

                foreach ($lists as $column => $formula) {
                    $validation = new DataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(false);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Giá trị không hợp lệ');
                    $validation->setError('Vui lòng chọn một giá trị trong danh sách.');
                    $validation->setFormula1($formula);

                    $sheet->getDelegate()->setDataValidation("{$column}2:{$column}2000", $validation);
                }

            }
        ];
    }
}
