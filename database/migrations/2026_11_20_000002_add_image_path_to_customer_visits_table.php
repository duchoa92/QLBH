<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            // Replaces the transient base64 thumbnail for new records. Images are private.
            $table->string('image_path')->nullable()->after('thumb');
        });
    }

    public function down(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            $table->dropColumn('image_path');
        });
    }
};
