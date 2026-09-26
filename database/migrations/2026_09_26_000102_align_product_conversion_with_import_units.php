<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier releases treated unit_id as the package unit. Stock was already
     * recorded in conversion_unit_id, so swap the definitions without changing
     * the actual stock quantities.
     */
    public function up(): void
    {
        DB::table('products')
            ->where('has_unit_conversion', true)
            ->whereNotNull('unit_id')
            ->whereNotNull('conversion_unit_id')
            ->orderBy('id')
            ->each(function (object $product): void {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'unit_id' => $product->conversion_unit_id,
                        'conversion_unit_id' => $product->unit_id,
                    ]);
            });
    }

    public function down(): void
    {
        // New products use the corrected meaning, so this data migration is not reversible.
    }
};
