<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_amount', 15, 2)->default(0);
            $table->json('opening_denominations')->nullable();
            $table->json('opening_bank_balances')->nullable();
            $table->decimal('cash_collected', 15, 2)->nullable();
            $table->decimal('customer_debt_amount', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('closing_amount', 15, 2)->nullable();
            $table->json('closing_denominations')->nullable();
            $table->json('closing_bank_balances')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'closed_at']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('work_shift_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_shift_id');
        });

        Schema::dropIfExists('work_shifts');
    }
};
