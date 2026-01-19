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
         if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->json('ai_diagnosis')->nullable();
                $table->decimal('ai_confidence', 5, 2)->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('repair_orders')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->dropColumn([
                    'ai_diagnosis',
                    'ai_confidence',
                ]);
            });
        }
    }
};
