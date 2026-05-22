<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_images', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', [
                'portrait',
                'cccd_front',
                'cccd_back',
                'with_device',
                'other',
            ])->default('portrait');

            $table->string('path');

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_images');
    }
};