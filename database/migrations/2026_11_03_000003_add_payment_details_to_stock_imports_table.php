<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->decimal('old_debt_paid', 18, 2)->default(0)->after('paid_amount');
            $table->string('payment_method', 20)->nullable()->after('old_debt_paid');
            $table->string('payment_reference', 80)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->dropColumn(['old_debt_paid', 'payment_method', 'payment_reference']);
        });
    }
};
