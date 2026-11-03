<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sale_items', 'vat_applied')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->boolean('vat_applied')->default(false)->after('has_vat');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_items', 'vat_applied')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn('vat_applied');
            });
        }
    }
};
