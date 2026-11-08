<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Một sheet dạng bảng đơn giản: tiêu đề cột + các dòng + danh sách cột tiền để định dạng #,##0. */
class TaxDeclarationSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $moneyColumns  chữ cái cột, ví dụ ['E', 'F']
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private array $moneyColumns = [],
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function columnFormats(): array
    {
        return collect($this->moneyColumns)->mapWithKeys(fn (string $column) => [$column => '#,##0'])->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
