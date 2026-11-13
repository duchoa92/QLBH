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
        Schema::create('stock_import_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('unit_name')
                ->nullable();

            $table->integer('quantity')->default(0);
            $table->unsignedInteger('base_quantity')->default(0);
            $table->unsignedInteger('conversion_factor')->default(1);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->boolean('has_vat')->default(false);
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('supplier_warranty_value')->nullable();
            $table->string('supplier_warranty_unit', 12)->nullable();
            $table->json('extra_info')->nullable();

            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_import_items');
    }
};
