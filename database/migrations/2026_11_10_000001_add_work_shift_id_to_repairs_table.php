<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gắn tiền thu khi trả máy sửa vào ca làm, giống hóa đơn bán hàng (sales.work_shift_id).
        Schema::table('repairs', function (Blueprint $table): void {
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['work_shift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropIndex(['work_shift_id', 'status']);
            $table->dropConstrainedForeignId('work_shift_id');
        });
    }
};
