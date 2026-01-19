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
       if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('last_visit_at')->nullable()->after('total_debt');
                $table->integer('visit_count')->default(0);
                $table->decimal('loyalty_score', 5, 2)->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn([
                    'last_visit_at',
                    'visit_count',
                    'loyalty_score',
                ]);
            });
        }
    }
};
