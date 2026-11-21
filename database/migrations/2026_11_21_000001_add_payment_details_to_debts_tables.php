<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Lưu chi tiết mỗi lần thanh toán công nợ để xem lại ở "Lịch sử thanh toán":
 * tiền khách đưa, tiền thừa, còn thiếu, nợ trước / sau. Các dòng cùng một lần thanh toán dùng chung payment_group.
 * Giao dịch cũ không có các cột này (NULL) vẫn hiển thị bình thường.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_debts', 'supplier_debts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->string('payment_group', 36)->nullable()->index();
                $table->decimal('received_amount', 18, 2)->nullable();
                $table->decimal('change_amount', 18, 2)->nullable();
                $table->decimal('shortage_amount', 18, 2)->nullable();
                $table->decimal('debt_before', 18, 2)->nullable();
                $table->decimal('debt_after', 18, 2)->nullable();

                if ($tableName === 'customer_debts') {
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_debts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });

        foreach (['customer_debts', 'supplier_debts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['payment_group']);
                $table->dropColumn(['payment_group', 'received_amount', 'change_amount', 'shortage_amount', 'debt_before', 'debt_after']);
            });
        }
    }
};
