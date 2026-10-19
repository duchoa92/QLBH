<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 40);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('payment_method', 20)->default('cash');
            $table->string('recipient', 150)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['expense_date', 'category']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
