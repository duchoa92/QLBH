<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code', 32)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->string('bank_account', 50);
            $table->unsignedBigInteger('expected_amount');
            $table->unsignedBigInteger('received_amount')->default(0);
            $table->json('checkout_payload');
            $table->string('status', 20)->default('waiting');
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->json('sale_response')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('bank_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 150)->unique();
            $table->foreignId('bank_payment_intent_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('description', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_payment_transactions');
        Schema::dropIfExists('bank_payment_intents');
    }
};
