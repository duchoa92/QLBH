<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_account_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_account', 'bank_account_name']);
        });
    }
};
