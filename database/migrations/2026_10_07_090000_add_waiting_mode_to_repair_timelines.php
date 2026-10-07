<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->string('waiting_mode', 20)->default('repair_now')->after('waiting_for_parts');
        });

        DB::table('repair_timelines')
            ->where('waiting_for_parts', true)
            ->update(['waiting_mode' => 'parts']);
    }

    public function down(): void
    {
        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->dropColumn('waiting_mode');
        });
    }
};
