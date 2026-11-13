<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_imports', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('party_type')->default('supplier');
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('party_name')->nullable();
            $table->string('party_phone', 30)->nullable();
            $table->string('party_cccd', 30)->nullable();
            $table->text('party_address')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('import_date')->nullable();

            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('extra_fee', 15, 2)->default(0);

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('tendered_amount', 18, 2)->default(0);
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->decimal('old_debt_paid', 18, 2)->default(0);
            $table->json('old_debt_allocations')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 80)->nullable();

            $table->text('note')->nullable();

            $table->string('status')->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_imports');
    }
};
