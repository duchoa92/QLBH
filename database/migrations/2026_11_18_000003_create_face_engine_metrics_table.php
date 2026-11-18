<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_engine_metrics', function (Blueprint $table): void {
            $table->id();
            $table->date('metric_date');
            $table->string('engine', 20);
            $table->unsignedBigInteger('frames')->default(0);
            $table->unsignedBigInteger('faces')->default(0);
            $table->unsignedBigInteger('processing_ms')->default(0);
            $table->timestamps();
            $table->unique(['metric_date', 'engine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_engine_metrics');
    }
};
