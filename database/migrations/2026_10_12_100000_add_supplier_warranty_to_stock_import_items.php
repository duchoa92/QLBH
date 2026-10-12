<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_import_items', 'supplier_warranty_value')) {
                $table->unsignedInteger('supplier_warranty_value')->nullable()->after('cost_price');
            }
            if (! Schema::hasColumn('stock_import_items', 'supplier_warranty_unit')) {
                $table->string('supplier_warranty_unit', 12)->nullable()->after('supplier_warranty_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('stock_import_items', 'supplier_warranty_unit')) {
                $columns[] = 'supplier_warranty_unit';
            }
            if (Schema::hasColumn('stock_import_items', 'supplier_warranty_value')) {
                $columns[] = 'supplier_warranty_value';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
