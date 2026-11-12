<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Linh kiện sửa chữa cũng phải trừ/hoàn theo lô như hàng bán và quà tặng. */
    public function up(): void
    {
        Schema::table('sale_item_stock_batches', function (Blueprint $table): void {
            $table->foreignId('repair_part_id')
                ->nullable()
                ->after('sale_item_gift_id')
                ->constrained('repair_parts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_item_stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repair_part_id');
        });
    }
};
