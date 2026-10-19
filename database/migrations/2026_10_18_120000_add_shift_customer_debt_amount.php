<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_shifts') || Schema::hasColumn('work_shifts', 'customer_debt_amount')) {
            return;
        }

        Schema::table('work_shifts', function (Blueprint $table): void {
            $table->decimal('customer_debt_amount', 15, 2)->nullable()->after('cash_collected');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('work_shifts') && Schema::hasColumn('work_shifts', 'customer_debt_amount')) {
            Schema::table('work_shifts', function (Blueprint $table): void {
                $table->dropColumn('customer_debt_amount');
            });
        }
    }
};
