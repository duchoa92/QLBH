<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->decimal('tendered_amount', 18, 2)->default(0)->after('paid_amount');
            $table->decimal('change_amount', 18, 2)->default(0)->after('tendered_amount');
        });
    }

    public function down(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->dropColumn(['tendered_amount', 'change_amount']);
        });
    }
};
