<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;

class ProductsExport implements FromCollection
{
    public function collection()
    {
        return Product::select(
            'name',
            'sku',
            'barcode',
            'cost_price',
            'sell_price',
            'stock'
        )->get();
    }
}
