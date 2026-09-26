<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imeis', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_imeis', 'extra_info')) {
                $table->json('extra_info')
                    ->nullable()
                    ->after('storage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_imeis', function (Blueprint $table): void {
            if (Schema::hasColumn('product_imeis', 'extra_info')) {
                $table->dropColumn('extra_info');
            }
        });
    }
};
