<?php

namespace App\Exports;

use App\Models\Product;
use App\Support\ProductImportColumns;
use App\Support\ProductSheetStyle;
use App\Support\SafeSpreadsheetValues;
use Maatwebsite\Excel\Concerns\{
    FromQuery,
    WithCustomValueBinder,
    WithEvents,
    WithHeadings,
    WithMapping,
    WithTitle,
};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;


class ProductsDataExport extends DefaultValueBinder implements
    FromQuery,
    WithCustomValueBinder,
    WithEvents,
    WithHeadings,
    WithMapping,
    WithTitle
{
    use SafeSpreadsheetValues;

    protected $export;

    protected int $rowNumber = 0;

    protected int $total;

    public function __construct($export)
    {
        $this->export = $export;
        $this->total = max(1, Product::count());
    }

    public function query()
    {
        // Số thứ tự đếm trong map() nên không cần ROW_NUMBER() (chạy được cả MySQL 5.7).
        return Product::with(['category', 'brand', 'unit'])
            ->orderBy('products.id');
    }

    public function headings(): array
    {
        return ProductImportColumns::HEADINGS;
    }

    public function map($p): array
    {
        // 👉 mỗi dòng chạy qua đây → update progress

        $this->rowNumber++;

        if ($this->rowNumber % 100 == 0) {
            $percent = intval(($this->rowNumber / $this->total) * 100);

            $this->export->update([
                'progress' => min($percent, 99)
            ]);
        }

        return [
            $this->rowNumber,
            $p->name,
            $p->sku,
            $p->model,
            $p->category?->name,
            $p->brand?->name,
            $p->sell_price,
            $p->cost_price,
            $p->stock,
            $p->unit?->short_name ?: $p->unit?->name,
            $p->product_type,
            $p->manage_stock_by_serial ? 1 : 0,
            $p->is_active ? 1 : 0,
            // Chỉ ghi tên file ảnh (không kèm thư mục) để nhập lại file này vẫn khớp ảnh đã lưu.
            $p->image ? basename($p->image) : '',
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
            AfterSheet::class => fn (AfterSheet $event) => ProductSheetStyle::apply($event, count(ProductImportColumns::HEADINGS)),
        ];
    }
}
