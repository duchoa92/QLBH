<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsExportReal implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Product::with(['category', 'brand'])
            ->get()
            ->map(function ($p) {
                return [
                    'name'     => $p->name,
                    'sku'      => $p->sku,
                    'price'    => $p->price,
                    'stock'    => $p->stock,
                    'category' => $p->category?->name,
                    'brand'    => $p->brand?->name,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'name',
            'sku',
            'price',
            'stock',
            'category',
            'brand',
        ];
    }
}