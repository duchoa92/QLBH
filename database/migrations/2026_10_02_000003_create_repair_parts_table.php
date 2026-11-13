<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration này thay thế 2026_10_02_000003_add_repair_completion_and_payment.
        // Môi trường đã chạy file cũ thì bảng đã tồn tại -> bỏ qua.
        if (Schema::hasTable('repair_parts')) {
            return;
        }

        Schema::create('repair_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_parts');
    }
};
