<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_import_items', 'unit_id')) {
                $table->foreignId('unit_id')
                    ->nullable()
                    ->after('variant_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('stock_import_items', 'unit_name')) {
                $table->string('unit_name')
                    ->nullable()
                    ->after('unit_id');
            }

            if (!Schema::hasColumn('stock_import_items', 'import_quantity')) {
                $table->decimal('import_quantity', 12, 2)
                    ->default(0)
                    ->after('unit_name');
            }

            if (!Schema::hasColumn('stock_import_items', 'conversion_factor')) {
                $table->decimal('conversion_factor', 12, 2)
                    ->default(1)
                    ->after('import_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_import_items', 'unit_id')) {
                $table->dropForeign(['unit_id']);
            }

            $columns = collect([
                'unit_id',
                'unit_name',
                'import_quantity',
                'conversion_factor',
            ])
                ->filter(fn ($column) => Schema::hasColumn('stock_import_items', $column))
                ->values()
                ->all();

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
