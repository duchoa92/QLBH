<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table): void {
            $table->json('extra_info')->nullable()->after('supplier_warranty_unit');
        });
    }

    public function down(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table): void {
            $table->dropColumn('extra_info');
        });
    }
};
