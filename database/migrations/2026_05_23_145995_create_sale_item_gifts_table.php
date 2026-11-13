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
        Schema::create('sale_item_gifts', function (
            Blueprint $table
        ) {

            $table->id();

            $table->foreignId('sale_item_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_imei_id')
                ->nullable()
                ->constrained('product_imeis')
                ->nullOnDelete();
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->integer('quantity')
                ->default(1);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->unsignedInteger('base_quantity')->default(1);
            $table->unsignedInteger('conversion_factor')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_item_gifts');
    }
};
