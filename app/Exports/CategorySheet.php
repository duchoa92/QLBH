<?php
namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromCollection;

class CategorySheet implements FromCollection
{
    public function collection()
    {
        return Category::select('name')->get();
    }
}