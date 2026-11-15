<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table): void {
            // Chu kỳ đóng: 1 = hàng tháng, 3 = hàng quý (3 tháng/lần), 6 = nửa năm, 12 = hàng năm.
            $table->unsignedTinyInteger('interval_months')->default(1)->after('due_day');
            // Tháng bắt đầu chu kỳ (Y-m). Trống = tính từ tháng tạo lịch. Hàng tháng thì không cần.
            $table->string('start_month', 7)->nullable()->after('interval_months');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_expenses', function (Blueprint $table): void {
            $table->dropColumn(['interval_months', 'start_month']);
        });
    }
};
