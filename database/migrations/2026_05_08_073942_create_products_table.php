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
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Quan hệ
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
            | Thông tin cơ bản
            |--------------------------------------------------------------------------
            */

            $table->string('name');

            $table->string('slug')
                ->unique();

            $table->string('sku')
                ->unique();

            $table->string('barcode')
                ->nullable()
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Giá
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
            | Kho
            |--------------------------------------------------------------------------
            */

            $table->integer('stock')
                ->default(0);

            $table->integer('alert_stock')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | IMEI / SERIAL
            |--------------------------------------------------------------------------
            */

            $table->boolean('has_imei')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Trạng thái
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            /*
            |--------------------------------------------------------------------------
            | Mô tả
            |--------------------------------------------------------------------------
            */

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Soft delete
            |--------------------------------------------------------------------------
            */

            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};