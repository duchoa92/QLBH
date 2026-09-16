<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'base_unit_id')) {
                $table->dropForeign(['base_unit_id']);
                $table->dropColumn('base_unit_id');
            }

            if (Schema::hasColumn('units', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('sale_items', 'unit_base_name') ? 'unit_base_name' : null,
                Schema::hasColumn('sale_items', 'sale_quantity') ? 'sale_quantity' : null,
                Schema::hasColumn('sale_items', 'conversion_factor') ? 'conversion_factor' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('stock_import_items', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('stock_import_items', 'import_quantity') ? 'import_quantity' : null,
                Schema::hasColumn('stock_import_items', 'conversion_factor') ? 'conversion_factor' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        //
    }
};
