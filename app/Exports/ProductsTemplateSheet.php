<?php

namespace App\Exports;

use App\Services\ProductImportService;
use App\Support\ProductImportColumns;
use App\Support\ProductSheetStyle;
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

    /** Số dòng có sẵn danh sách chọn / định dạng văn bản: bằng giới hạn nhập mỗi lần (+ dòng tiêu đề). */
    private const LAST_ROW = ProductImportService::MAX_ROWS + 1;

    /**
     * Dòng ví dụ (mỗi dòng minh họa một trường hợp). Thứ tự cột theo ProductImportColumns::HEADINGS:
     * STT, Tên, SKU, Model, Danh mục, Thương hiệu, Giá bán, Giá nhập, Tồn kho, Đơn vị, Loại, Có IMEI, Trạng thái, Ảnh, Mô tả.
     */
    public function array(): array
    {
        return [
            [1, 'Camera Ezviz H8C 2K', 'CAM-EZVIZ-H8C', 'H8C', 'Camera', 'Ezviz', 650000, 520000, 0, 'Cái', 'imei', 1, 1, '', 'Sản phẩm quản lý IMEI: để tồn kho = 0, nhập hàng qua phiếu nhập.'],
            [2, 'Dây mạng Cat6 UTP', 'DAY-CAT6', '', 'Phụ kiện', '', 5000, 3000, 100, 'Mét', 'normal', 0, 1, '', 'Hàng thường có tồn đầu kỳ 100 mét (tự tạo lô tồn đầu kỳ).'],
            [3, 'Công lắp đặt camera', 'DV-LAP-CAM', '', 'Dịch vụ', '', 200000, 0, 0, 'Lần', 'service', 0, 1, '', 'Dịch vụ: không có tồn kho.'],
            [4, 'Củ sạc 20W (ngừng bán)', 'SAC-20W', '20W-PD', 'Phụ kiện', 'Anker', 250000, 150000, 0, 'Cái', 'normal', 0, 0, '', 'Trạng thái 0 = ngừng kinh doanh.'],
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

                ProductSheetStyle::apply($event, count(ProductImportColumns::HEADINGS));

                /* ===== 5. SKU / MODEL LÀ VĂN BẢN (giữ số 0 đầu) ===== */
                $sheet->getStyle('C2:D' . self::LAST_ROW)->getNumberFormat()->setFormatCode('@');

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

                    $sheet->getDelegate()->setDataValidation("{$column}2:{$column}" . self::LAST_ROW, $validation);
                }

            }
        ];
    }
}
