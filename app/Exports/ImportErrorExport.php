<?php

namespace App\Exports;

use App\Support\ProductImportColumns;
use App\Support\ProductSheetStyle;
use App\Support\SafeSpreadsheetValues;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class ImportErrorExport extends DefaultValueBinder implements FromArray, WithHeadings, WithEvents, WithTitle, WithCustomValueBinder
{
    use SafeSpreadsheetValues;

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
                $e['model'] ?? '',
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
            AfterSheet::class => fn (AfterSheet $event) => ProductSheetStyle::apply($event, count(ProductImportColumns::headingsWithError())),
        ];
    }
}
