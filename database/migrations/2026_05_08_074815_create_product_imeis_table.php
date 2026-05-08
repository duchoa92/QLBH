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
        Schema::create('product_imeis', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Quan hệ
            |--------------------------------------------------------------------------
            */

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | IMEI / SERIAL
            |--------------------------------------------------------------------------
            */

            $table->string('imei')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Thông tin máy
            |--------------------------------------------------------------------------
            */

            $table->string('serial')
                ->nullable();

            $table->string('color')
                ->nullable();

            $table->string('storage')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Giá riêng từng máy
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
            | Trạng thái
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [

                'in_stock',

                'sold',

                'repair',

                'returned',

            ])->default('in_stock');

            /*
            |--------------------------------------------------------------------------
            | Ngày bán
            |--------------------------------------------------------------------------
            */

            $table->timestamp('sold_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Ghi chú
            |--------------------------------------------------------------------------
            */

            $table->text('note')
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
        Schema::dropIfExists('product_imeis');
    }
};