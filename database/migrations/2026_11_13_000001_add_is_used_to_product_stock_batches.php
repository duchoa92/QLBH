<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lô hàng nhập từ khách (sản phẩm cũ) phải được nhận biết là hàng cũ khi bán ở POS. */
    public function up(): void
    {
        if (! Schema::hasColumn('product_stock_batches', 'is_used')) {
            Schema::table('product_stock_batches', function (Blueprint $table): void {
                $table->boolean('is_used')->default(false)->after('sell_price');
            });
        }

        // Các lô đã có: lấy theo dòng phiếu nhập tạo ra lô.
        DB::table('product_stock_batches')
            ->whereIn('stock_import_item_id', DB::table('stock_import_items')->where('is_used', true)->select('id'))
            ->update(['is_used' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_stock_batches', 'is_used')) {
            Schema::table('product_stock_batches', function (Blueprint $table): void {
                $table->dropColumn('is_used');
            });
        }
    }
};
