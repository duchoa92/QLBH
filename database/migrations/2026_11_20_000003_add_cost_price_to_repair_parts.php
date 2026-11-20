<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('repair_parts', 'cost_price')) {
            Schema::table('repair_parts', function (Blueprint $table): void {
                $table->decimal('cost_price', 15, 2)->default(0)->after('unit_price');
            });
        }
        if (Schema::hasTable('sale_item_stock_batches') && Schema::hasColumn('sale_item_stock_batches', 'repair_part_id')) {
            \Illuminate\Support\Facades\DB::table('repair_parts')
                ->whereIn('id', \Illuminate\Support\Facades\DB::table('sale_item_stock_batches')->whereNotNull('repair_part_id')->select('repair_part_id'))
                ->update(['cost_price' => \Illuminate\Support\Facades\DB::raw('(SELECT COALESCE(SUM(a.quantity * a.unit_cost) / NULLIF(SUM(a.quantity), 0), 0) FROM sale_item_stock_batches a WHERE a.repair_part_id = repair_parts.id)')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('repair_parts', 'cost_price')) {
            Schema::table('repair_parts', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        }
    }
};
