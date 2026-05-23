<?php

declare(strict_types=1);

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
        Schema::create('suppliers', function (Blueprint $table): void {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | BASIC INFO
            |--------------------------------------------------------------------------
            */

            $table->string('code')
                ->unique();

            $table->string('name');

            $table->string('contact_person')
                ->nullable();

            $table->string('phone', 30)
                ->nullable()
                ->index();

            $table->string('email')
                ->nullable();

            $table->string('tax_code')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ADDRESS
            |--------------------------------------------------------------------------
            */

            $table->string('province')
                ->nullable();

            $table->string('district')
                ->nullable();

            $table->string('ward')
                ->nullable();

            $table->string('address')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | BUSINESS
            |--------------------------------------------------------------------------
            */

            $table->decimal('debt_balance', 18, 2)
                ->default(0);

            $table->decimal('total_purchase', 18, 2)
                ->default(0);

            $table->unsignedBigInteger('total_orders')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | NOTE
            |--------------------------------------------------------------------------
            */

            $table->text('note')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};