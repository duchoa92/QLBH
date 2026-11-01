<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sale_items', 'has_vat')) {
            Schema::table('sale_items', function (Blueprint $table): void {
                $table->boolean('has_vat')->default(false)->after('tax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_items', 'has_vat')) {
            Schema::table('sale_items', function (Blueprint $table): void {
                $table->dropColumn('has_vat');
            });
        }
    }
};
