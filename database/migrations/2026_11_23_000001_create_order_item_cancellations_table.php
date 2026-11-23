<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lưu lại từng sản phẩm bị hủy riêng lẻ khỏi đơn bán / đơn nhập.
 *
 * Dòng sản phẩm bị hủy được xóa khỏi sale_items / stock_import_items (nên báo cáo,
 * tồn kho, tra cứu IMEI... tự đúng, không phải sửa từng truy vấn) và được ghi lại ở đây
 * để màn hình chi tiết vẫn hiện "đã hủy sản phẩm nào, vì sao, lúc nào, bởi ai".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_cancellations', function (Blueprint $table) {
            $table->id();
            $table->string('order_type');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->json('imeis')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('unit_name', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->json('meta')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['order_type', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_cancellations');
    }
};
