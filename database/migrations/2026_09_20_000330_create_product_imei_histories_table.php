<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_imei_histories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_imei_id')
                ->nullable()
                ->constrained('product_imeis')
                ->nullOnDelete();

            $table->string('imei')->index();
            $table->string('type')->index();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->decimal('sell_price', 15, 2)->nullable();
            $table->json('meta')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('happened_at')->nullable()->index();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imei_histories');
    }
};
