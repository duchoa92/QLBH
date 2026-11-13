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
        $supportsFullText = Schema::getConnection()->getDriverName() !== 'sqlite';

        Schema::create('products', function (Blueprint $table) use ($supportsFullText) {

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
            $table->boolean('has_unit_conversion')->default(false);
            $table->foreignId('conversion_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();
            $table->unsignedInteger('conversion_factor')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->string('search_text')->nullable();

            if ($supportsFullText) {
                $table->fullText('search_text');
            }

            $table->string('slug')
                ->unique();

            $table->string('sku')->nullable()->unique();

            $table->string('model')->nullable();

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

            $table->integer('stock')->default(0);

            $table->unsignedBigInteger('sold_count')
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

            $table->index('model');

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
