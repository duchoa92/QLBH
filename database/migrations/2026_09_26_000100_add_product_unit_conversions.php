<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_unit_conversion')
                ->default(false)
                ->after('unit_id');
            $table->foreignId('conversion_unit_id')
                ->nullable()
                ->after('has_unit_conversion')
                ->constrained('units')
                ->nullOnDelete();
            $table->unsignedInteger('conversion_factor')
                ->default(1)
                ->after('conversion_unit_id');
        });

        Schema::table('stock_import_items', function (Blueprint $table) {
            $table->unsignedInteger('base_quantity')
                ->default(0)
                ->after('quantity');
            $table->unsignedInteger('conversion_factor')
                ->default(1)
                ->after('base_quantity');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('base_quantity')
                ->default(1)
                ->after('quantity');
            $table->unsignedInteger('conversion_factor')
                ->default(1)
                ->after('base_quantity');
        });

        Schema::table('sale_item_gifts', function (Blueprint $table) {
            $table->unsignedInteger('base_quantity')
                ->default(1)
                ->after('quantity');
            $table->unsignedInteger('conversion_factor')
                ->default(1)
                ->after('base_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['base_quantity', 'conversion_factor']);
        });

        Schema::table('stock_import_items', function (Blueprint $table) {
            $table->dropColumn(['base_quantity', 'conversion_factor']);
        });

        Schema::table('sale_item_gifts', function (Blueprint $table) {
            $table->dropColumn(['base_quantity', 'conversion_factor']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['conversion_unit_id']);
            $table->dropColumn([
                'has_unit_conversion',
                'conversion_unit_id',
                'conversion_factor',
            ]);
        });
    }
};
