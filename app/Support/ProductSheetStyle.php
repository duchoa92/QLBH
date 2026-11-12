<?php

namespace App\Support;

use Maatwebsite\Excel\Events\AfterSheet;

/** Định dạng dùng chung cho các sheet sản phẩm (mẫu, xuất dữ liệu, file lỗi) để không lặp code. */
class ProductSheetStyle
{
    /** Tiêu đề đậm, viền, cột bắt buộc (tiêu đề có dấu *) tô đỏ, cố định dòng đầu, tự giãn cột. */
    public static function apply(AfterSheet $event, int $columnCount): void
    {
        $sheet = $event->sheet;
        $lastColumn = chr(64 + $columnCount);

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin']],
        ]);

        foreach (ProductImportColumns::HEADINGS as $index => $heading) {
            if (str_ends_with($heading, '*')) {
                $sheet->getStyle(chr(65 + $index) . '1')->getFont()->getColor()->setARGB('FFFF0000');
            }
        }

        $sheet->freezePane('A2');

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
