<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table): void {
            $table->id();
            // Trống = khách lạ chưa có thông tin; được gán sau khi nhân viên nhập tên/SĐT.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // true = hệ thống tự nhận ra khách đã đăng ký khuôn mặt; false = khách lạ (có thể được gán tên sau).
            $table->boolean('recognized')->default(false);
            // Vector đặc trưng của KHÁCH LẠ để gộp các lần thấy cùng một người và gán tên sau. Xóa ngay khi đã gán/hết hạn lưu.
            $table->json('descriptor')->nullable();
            // Ảnh nhỏ (JPEG ~64px) để nhân viên nhận ra khách khi gán tên. Tự xóa khi hết thời hạn lưu.
            $table->text('thumb')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index('first_seen_at');
            $table->index(['customer_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
