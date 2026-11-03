<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('bank_bin', 6)->nullable()->after('bank_name');
            $table->string('bank_payment_url', 2048)->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['bank_bin', 'bank_payment_url']);
        });
    }
};
