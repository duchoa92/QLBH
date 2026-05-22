<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_debts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', ['increase', 'decrease']);

            $table->decimal('amount', 15, 2);

            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_debts');
    }
};