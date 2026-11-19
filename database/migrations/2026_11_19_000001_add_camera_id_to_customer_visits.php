<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            $table->string('camera_id', 64)->nullable()->after('user_id')->index();
        });

        // Records created before multi-camera support came from the original
        // entrance camera. Keeping that provenance makes the statistics page
        // useful immediately instead of showing an ambiguous blank source.
        DB::table('customer_visits')->whereNull('camera_id')->update(['camera_id' => 'door-1']);
    }

    public function down(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            $table->dropIndex(['camera_id']);
            $table->dropColumn('camera_id');
        });
    }
};
