<?php

declare(strict_types=1);

use App\Models\Repair;
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
        Schema::create('repair_images', function (Blueprint $table): void {

            $table->id();

            $table->foreignIdFor(Repair::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->string('image_path');

            $table->timestamps();
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repair_images');
    }
};