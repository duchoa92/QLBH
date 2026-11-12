<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_import_items', 'is_used')) {
            Schema::table('stock_import_items', function (Blueprint $table) {
                $table->boolean('is_used')->default(false)->after('has_vat');
            });
        }

        if (! Schema::hasColumn('product_imeis', 'is_used')) {
            Schema::table('product_imeis', function (Blueprint $table) {
                $table->boolean('is_used')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_import_items', 'is_used')) {
            Schema::table('stock_import_items', fn (Blueprint $table) => $table->dropColumn('is_used'));
        }

        if (Schema::hasColumn('product_imeis', 'is_used')) {
            Schema::table('product_imeis', fn (Blueprint $table) => $table->dropColumn('is_used'));
        }
    }
};
