<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_item_gifts', function (Blueprint $table): void {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('sale_item_gifts', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
