<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 40);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->unsignedTinyInteger('due_day');
            $table->unsignedTinyInteger('remind_days')->default(7);
            $table->string('payment_method', 20)->default('cash');
            $table->string('recipient', 150)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'due_day']);
        });

        Schema::create('recurring_expense_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->date('paid_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['recurring_expense_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_payments');
        Schema::dropIfExists('recurring_expenses');
    }
};
