<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_payment_intents', function (Blueprint $table): void {
            $table->dropForeign(['work_shift_id']);
            $table->foreignId('work_shift_id')
                ->nullable()
                ->change();
            $table->foreign('work_shift_id')
                ->references('id')
                ->on('work_shifts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('bank_payment_intents')->whereNull('work_shift_id')->exists()) {
            throw new RuntimeException('Cannot require a work shift while bank payment intents without a shift exist.');
        }

        Schema::table('bank_payment_intents', function (Blueprint $table): void {
            $table->dropForeign(['work_shift_id']);
            $table->foreignId('work_shift_id')
                ->nullable(false)
                ->change();
            $table->foreign('work_shift_id')
                ->references('id')
                ->on('work_shifts')
                ->cascadeOnDelete();
        });
    }
};
