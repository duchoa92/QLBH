<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TaxDeclarationExport implements WithMultipleSheets
{
    /** @param  array<int, TaxDeclarationSheet>  $sheets */
    public function __construct(private array $sheets)
    {
    }

    public function sheets(): array
    {
        return $this->sheets;
    }
}
