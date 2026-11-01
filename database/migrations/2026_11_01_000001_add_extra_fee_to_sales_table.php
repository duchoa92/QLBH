<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'invoice_discount')) {
                $table->decimal('invoice_discount', 15, 2)->default(0)->after('discount');
            }
            if (! Schema::hasColumn('sales', 'extra_fee')) {
                $table->decimal('extra_fee', 15, 2)->default(0)->after('tax');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            if (Schema::hasColumn('sales', 'extra_fee')) {
                $table->dropColumn('extra_fee');
            }
            if (Schema::hasColumn('sales', 'invoice_discount')) {
                $table->dropColumn('invoice_discount');
            }
        });
    }
};
