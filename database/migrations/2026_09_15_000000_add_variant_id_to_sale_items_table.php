<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run migrations.
     *
     * Bổ sung cột variant_id cho sale_items để lưu đúng biến thể đã bán
     * (trước đây chỉ lưu product_id, không phân biệt được đã bán biến thể nào).
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {

            $table->foreignId('variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {

            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
