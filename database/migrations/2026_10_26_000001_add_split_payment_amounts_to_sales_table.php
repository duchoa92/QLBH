<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('cash_received_amount', 15, 2)->default(0)->after('paid_amount');
            $table->decimal('bank_received_amount', 15, 2)->default(0)->after('cash_received_amount');
        });

        DB::table('sales')->where('payment_method', 'cash')->update(['cash_received_amount' => DB::raw('paid_amount')]);
        DB::table('sales')->where('payment_method', 'bank')->update(['bank_received_amount' => DB::raw('paid_amount')]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['cash_received_amount', 'bank_received_amount']);
        });
    }
};
