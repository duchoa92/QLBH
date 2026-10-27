<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_import_items', 'has_vat')) {
            Schema::table('stock_import_items', function (Blueprint $table) {
                $table->boolean('has_vat')->default(false)->after('cost_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_import_items', 'has_vat')) {
            Schema::table('stock_import_items', function (Blueprint $table) {
                $table->dropColumn('has_vat');
            });
        }
    }
};
