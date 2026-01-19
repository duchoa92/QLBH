<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->string('device_brand')->nullable()->after('device_name');
            $table->string('device_model')->nullable()->after('device_brand');
            $table->string('root_cause')->nullable()->after('issue_description');
            $table->integer('repair_duration')->nullable()->after('repair_cost'); // phút
            $table->string('success_level')->nullable()->after('repair_duration'); // ok / retry
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn([
                'device_brand',
                'device_model',
                'root_cause',
                'repair_duration',
                'success_level'
            ]);
        });
    }
};
