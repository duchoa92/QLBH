<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            $table->foreignId('sale_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('product_imei_id')
                ->nullable()
                ->constrained('product_imeis')
                ->nullOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->string('unit_name')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */

            $table->integer('quantity')
                ->default(1);
            $table->unsignedInteger('base_quantity')->default(1);
            $table->unsignedInteger('conversion_factor')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Price
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'unit_price',
                15,
                2
            );
            $table->decimal('unit_cost', 15, 2)->nullable();

            $table->decimal(
                'discount',
                15,
                2
            )->default(0);

            $table->decimal(
                'tax',
                15,
                2
            )->default(0);
            $table->boolean('has_vat')->default(false);
            $table->boolean('vat_applied')->default(false);

            $table->decimal(
                'subtotal',
                15,
                2
            );

            $table->text('note')
                ->nullable();
            

            $table->enum(
                'discount_type',
                [
                    'amount',
                    'percent',
                ]
            )->nullable();

            $table->decimal(
                'discount_value',
                15,
                2
            )->default(0);
            $table->unsignedInteger('warranty_days')->default(0);
            $table->unsignedInteger('warranty_value')->nullable();
            $table->string('warranty_unit', 12)->nullable();
            $table->text('warranty_note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
