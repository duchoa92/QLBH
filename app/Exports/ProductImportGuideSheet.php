<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/** Sheet hướng dẫn điền file nhập sản phẩm (đặt cuối để sheet đầu tiên vẫn là sheet dữ liệu). */
class ProductImportGuideSheet implements FromArray, WithHeadings, WithTitle, WithEvents
{
    public function headings(): array
    {
        return ['Cột', 'Bắt buộc', 'Giá trị hợp lệ / Ghi chú'];
    }

    public function array(): array
    {
        return [
            ['Tên sản phẩm', 'Có', 'Tối đa 255 ký tự.'],
            ['SKU', 'Có', 'Mã duy nhất, tối đa 100 ký tự. Không phân biệt hoa/thường và dấu. Không được trùng sản phẩm đang có hoặc sản phẩm trong thùng rác.'],
            ['Model', 'Không', 'Model/mã hiệu sản phẩm (không cần duy nhất), tối đa 100 ký tự.'],
            ['Danh mục', 'Có', 'Nên chọn đúng tên trong sheet "Danh mục". Tên chưa có sẽ được TẠO MỚI (xem cảnh báo ở bước kiểm tra).'],
            ['Thương hiệu', 'Không', 'Tên chưa có sẽ được tạo mới.'],
            ['Giá bán', 'Có', 'Số từ 0 trở lên. Ví dụ 650000 hoặc 650.000 (dấu chấm là phân cách hàng nghìn).'],
            ['Giá nhập', 'Không', 'Số từ 0 trở lên, bỏ trống = 0.'],
            ['Tồn kho', 'Không', 'Số nguyên từ 0 trở lên. Sản phẩm IMEI phải để 0 và nhập tồn qua phiếu nhập hàng. Tồn > 0 sẽ tự tạo lô tồn đầu kỳ.'],
            ['Đơn vị tính', 'Không', 'Tên chưa có sẽ được tạo mới.'],
            ['Loại sản phẩm', 'Không', 'normal (mặc định) | imei | service | combo. Chọn trong danh sách thả xuống.'],
            ['Có IMEI', 'Không', '1 = quản lý theo IMEI/Serial, 0 = không. Nếu bằng 1 thì sản phẩm tự thành loại imei.'],
            ['Trạng thái', 'Không', '1 = đang kinh doanh (mặc định), 0 = ngừng.'],
            ['Ảnh', 'Không', 'Tên file ảnh (vd. camera-h8c.jpg) và chọn ảnh ở bước nhập. Chỉ khớp chính xác tên, mỗi tên ảnh chỉ được dùng cho một file; hỗ trợ jpg, png, webp, gif (tối đa 5MB/ảnh).'],
            ['Mô tả', 'Không', 'Văn bản tự do.'],
            ['', '', ''],
            ['Lưu ý chung', '', 'Hãy xóa 2 dòng ví dụ trước khi nhập. Tối đa 5.000 sản phẩm mỗi lần. Dòng có lỗi sẽ được bỏ qua và xuất thành file lỗi để sửa.'],
        ];
    }

    public function title(): string
    {
        return 'Hướng dẫn';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(12);
                $sheet->getColumnDimension('C')->setWidth(110);
                $sheet->getStyle('A2:C20')->getAlignment()->setWrapText(true);
                $sheet->getStyle('A2:C20')->getAlignment()->setVertical('top');
            },
        ];
    }
}
