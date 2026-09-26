<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Các chứng từ cũ chưa có quy đổi: số lượng hiển thị chính là số lượng tồn kho.
        DB::table('stock_import_items')
            ->where('conversion_factor', 1)
            ->update(['base_quantity' => DB::raw('quantity')]);

        DB::table('sale_items')
            ->where('conversion_factor', 1)
            ->update(['base_quantity' => DB::raw('quantity')]);

        DB::table('sale_item_gifts')
            ->where('conversion_factor', 1)
            ->update(['base_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        // Không hoàn nguyên dữ liệu lịch sử đã chuẩn hóa.
    }
};
