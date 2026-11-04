<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->json('old_debt_allocations')->nullable()->after('old_debt_paid');
        });
    }

    public function down(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->dropColumn('old_debt_allocations');
        });
    }
};
