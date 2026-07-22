<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class GuideSheet implements FromArray
{
    public function array(): array
    {
        return [
            ['HƯỚNG DẪN'],
            ['- Không xóa header'],
            ['- category_id phải tồn tại'],
            ['- brand_id phải tồn tại'],
            ['- price là số'],
            ['- stock >= 0'],
        ];
    }
}