<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->string('party_type')->default('supplier')->after('supplier_id');
            $table->foreignId('customer_id')->nullable()->after('party_type')->constrained()->nullOnDelete();
            $table->string('party_name')->nullable()->after('customer_id');
            $table->string('party_phone', 30)->nullable()->after('party_name');
            $table->string('party_cccd', 30)->nullable()->after('party_phone');
            $table->text('party_address')->nullable()->after('party_cccd');
        });
    }

    public function down(): void
    {
        Schema::table('stock_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['party_type', 'party_name', 'party_phone', 'party_cccd', 'party_address']);
        });
    }
};
