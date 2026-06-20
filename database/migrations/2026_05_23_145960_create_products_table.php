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
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('unit_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->string('search_text')->nullable();

            $table->fullText('search_text');

            $table->string('slug')
                ->unique();

            $table->string('sku')
                ->unique();

            $table->string('barcode')
                ->nullable()
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Product Type
            |--------------------------------------------------------------------------
            */

            $table->enum(
                'product_type',
                [
                    'normal',
                    'imei',
                    'service',
                    'combo',
                ]
            )->default('normal');

            /*
            |--------------------------------------------------------------------------
            | Image
            |--------------------------------------------------------------------------
            */

            $table->string('image')
                ->nullable()
                ->default(null);

            /*
            |--------------------------------------------------------------------------
            | Warranty
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('warranty_days')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Allow Negative Stock
            |--------------------------------------------------------------------------
            */

            $table->boolean('allow_negative_stock')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Prices
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'cost_price',
                15,
                2
            )->default(0);

            $table->decimal(
                'sell_price',
                15,
                2
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Tax
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'tax_percent',
                5,
                2
            )->default(0);

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('stock')
                ->default(0);

            $table->unsignedInteger('alert_stock')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | IMEI / SERIAL
            |--------------------------------------------------------------------------
            */

            $table->boolean('manage_stock_by_serial')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('name');

            $table->index('sku');

            $table->index('barcode');

            $table->index('product_type');

            $table->index('is_active');

            /*
            |--------------------------------------------------------------------------
            | Soft Deletes
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};