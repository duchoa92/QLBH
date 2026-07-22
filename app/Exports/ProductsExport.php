<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductsExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new \App\Exports\ProductsSheet(),
            new \App\Exports\CategorySheet(),
            new \App\Exports\BrandSheet(),
        ];
    }
}