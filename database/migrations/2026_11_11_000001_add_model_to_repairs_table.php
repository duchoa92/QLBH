<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('repairs', 'model')) {
            return;
        }

        Schema::table('repairs', function (Blueprint $table) {
            $table->string('model', 100)->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('repairs', 'model')) {
            return;
        }

        Schema::table('repairs', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
