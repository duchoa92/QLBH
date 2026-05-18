<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (
            Blueprint $table
        ) {

            $table->id();

            $table->string('code')
                ->unique();

            $table->string(
                'customer_name'
            );

            $table->string(
                'customer_phone'
            )->nullable();

            $table->string(
                'device_name'
            );

            $table->string(
                'imei'
            )->nullable();

            $table->text(
                'issue'
            );

            $table->decimal(
                'repair_fee',
                15,
                2
            )->default(0);

            $table->enum(
                'status',
                [
                    'pending',
                    'repairing',
                    'completed',
                    'returned',
                ]
            )->default('pending');

            $table->text(
                'note'
            )->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'repairs'
        );
    }
};