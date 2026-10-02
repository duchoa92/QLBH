<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->json('issue')->nullable()->after('description');
            $table->text('parts_needed')->nullable()->after('issue');
            $table->unsignedInteger('expected_days')->nullable()->after('parts_needed');
        });

        Schema::table('repair_images', function (Blueprint $table): void {
            $table->foreignId('timeline_id')->nullable()->after('repair_id')
                ->constrained('repair_timelines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_images', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('timeline_id');
        });

        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->dropColumn(['issue', 'parts_needed', 'expected_days']);
        });
    }
};
