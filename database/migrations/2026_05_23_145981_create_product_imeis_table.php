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
        Schema::create('product_imeis', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relations
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('sale_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Device Information
            |--------------------------------------------------------------------------
            */

            $table->string('imei')
                ->nullable()
                ->unique();

            $table->string('serial')
                ->nullable()
                ->unique();

            $table->string('color')
                ->nullable();

            $table->string('storage')
                ->nullable();



            
            /*
            |--------------------------------------------------------------------------
            | Device Condition
            |--------------------------------------------------------------------------
            */

            $table->string('condition')
                ->default('new');

            /*
            |--------------------------------------------------------------------------
            | Battery Health
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('battery_health')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Prices
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'purchase_price',
                15,
                2
            )->default(0);

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
            | Warranty
            |--------------------------------------------------------------------------
            */

            $table->timestamp('warranty_expired_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Imported At
            |--------------------------------------------------------------------------
            */

            $table->timestamp('imported_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sold At
            |--------------------------------------------------------------------------
            */

            $table->timestamp('sold_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->tinyInteger('status')
                ->default(0)
                ->comment(
                    '0=available,1=sold,2=repairing,3=returned'
                );

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('note')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('imei');

            $table->index('serial');

            $table->index('status');

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
        Schema::dropIfExists('product_imeis');
    }
};