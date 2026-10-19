<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('warranty_days')
                ->default(0)
                ->after('discount_value');

            $table->unsignedInteger('warranty_value')
                ->nullable()
                ->after('warranty_days');

            $table->string('warranty_unit', 12)
                ->nullable()
                ->after('warranty_value');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'warranty_days',
                'warranty_value',
                'warranty_unit',
            ]);
        });
    }
};
