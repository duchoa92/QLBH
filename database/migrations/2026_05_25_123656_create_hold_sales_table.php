<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hold_sales',
            function (
                Blueprint $table
            ) {

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | User
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'user_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Customer
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'customer_id'
                )
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Note
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'name'
                )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Cart JSON
                |--------------------------------------------------------------------------
                */

                $table->json(
                    'cart_items'
                );

                /*
                |--------------------------------------------------------------------------
                | Total
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'grand_total',
                    15,
                    2
                )
                    ->default(0);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hold_sales'
        );
    }
};