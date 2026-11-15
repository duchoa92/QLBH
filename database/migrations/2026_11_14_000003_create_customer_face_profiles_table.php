<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_face_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Chỉ lưu vector đặc trưng 128 số, KHÔNG lưu ảnh khuôn mặt.
            $table->json('descriptor');
            // Khách đã đồng ý lưu dữ liệu nhận diện: thời điểm và nhân viên ghi nhận.
            $table->timestamp('consented_at');
            $table->foreignId('consented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_face_profiles');
    }
};
