<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customers')
            ->select(['id', 'cccd'])
            ->whereNotNull('cccd')
            ->orderBy('id')
            ->chunkById(500, function ($customers): void {
                foreach ($customers as $customer) {
                    $cccd = preg_replace('/\D+/', '', (string) $customer->cccd) ?? '';
                    DB::table('customers')->where('id', $customer->id)->update([
                        'cccd' => $cccd === '' ? null : $cccd,
                    ]);
                }
            });

        $hasDuplicates = DB::table('customers')
            ->select('cccd')
            ->whereNotNull('cccd')
            ->groupBy('cccd')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Cannot make customer CCCD unique: duplicate CCCDs exist after normalization.');
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_cccd_index');
            $table->unique('cccd', 'customers_cccd_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_cccd_unique');
            $table->index('cccd', 'customers_cccd_index');
        });
    }
};
