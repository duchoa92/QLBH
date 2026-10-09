<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->decimal('paid_amount', 18, 2)->default(0)->after('grand_total');
        });
        Schema::create('supplier_debts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['increase', 'decrease']);
            $table->decimal('amount', 18, 2);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_debts');
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->dropColumn('paid_amount');
        });
    }
};
