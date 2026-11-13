<?php

declare(strict_types=1);

use App\Models\Repair;
use App\Models\User;
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
        Schema::create('repair_timelines', function (Blueprint $table): void {

            $table->id();

            $table->foreignIdFor(Repair::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(User::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Timeline
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->nullable();

            $table->string('title');

            $table->longText('description')
                ->nullable();
            $table->json('issue')->nullable();
            $table->text('parts_needed')->nullable();
            $table->boolean('waiting_for_parts')->default(false);
            $table->string('waiting_mode', 20)->default('repair_now');
            $table->unsignedInteger('expected_days')->nullable();

            $table->timestamps();
        });

        // repair_images được tạo trước repair_timelines nên không thể khai báo
        // khóa ngoại timeline_id ngay trong migration của nó; bổ sung tại đây.
        Schema::table('repair_images', function (Blueprint $table): void {
            $table->foreignId('timeline_id')
                ->nullable()
                ->after('repair_id')
                ->constrained('repair_timelines')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse migrations.
     */
    public function down(): void
    {
        Schema::table('repair_images', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('timeline_id');
        });

        Schema::dropIfExists(
            'repair_timelines'
        );
    }
};