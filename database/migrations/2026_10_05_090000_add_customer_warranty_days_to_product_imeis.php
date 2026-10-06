<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imeis', function (Blueprint $table): void {
            $table->unsignedInteger('customer_warranty_days')->nullable()->after('customer_warranty_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_imeis', function (Blueprint $table): void {
            $table->dropColumn('customer_warranty_days');
        });
    }
};
