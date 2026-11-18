<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_face_profiles', function (Blueprint $table): void {
            // Vector from one model must never be compared to a vector from another model.
            $table->string('engine', 20)->default('human-v1')->after('descriptor');
            $table->index(['customer_id', 'engine']);
        });

        Schema::table('customer_visits', function (Blueprint $table): void {
            // Only meaningful when descriptor is present (unrecognised visit).
            $table->string('descriptor_engine', 20)->nullable()->after('descriptor');
            $table->index(['descriptor_engine', 'last_seen_at']);
        });

        // Historical descriptors in this project were created by Human.
        DB::table('customer_visits')->whereNotNull('descriptor')->update(['descriptor_engine' => 'human-v1']);
    }

    public function down(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            $table->dropIndex(['descriptor_engine', 'last_seen_at']);
            $table->dropColumn('descriptor_engine');
        });
        Schema::table('customer_face_profiles', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'engine']);
            $table->dropColumn('engine');
        });
    }
};
