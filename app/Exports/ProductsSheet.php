<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ProductsSheet implements FromArray
{
    public function array(): array
    {
        return [
            ['name','sku','price','stock','category','brand','imeis'],
            ['iPhone 11','IP11',5000000,10,'Apple','Apple','123,456'],
        ];
    }
}