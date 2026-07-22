<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use App\Models\Brand;

class BrandSheet implements FromCollection
{
    public function collection()
    {
        return Brand::with('category')
            ->get()
            ->map(function ($b) {
                return [
                    'name' => $b->name,
                    'category' => $b->category?->name,
                ];
            });
    }
}