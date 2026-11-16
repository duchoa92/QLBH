<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Chuyển nhận diện từ face-api (vector 128 số) sang Human (vector dài hơn, không so khớp được với dữ liệu cũ).
| Vector cũ vô dụng với mô hình mới nên xóa; khách cần đăng ký lại khuôn mặt (dữ liệu hiện tại là dữ liệu thử).
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_face_profiles')->delete();
        DB::table('customer_visits')->whereNotNull('descriptor')->update(['descriptor' => null]);
    }

    public function down(): void
    {
        // Không khôi phục được vector đã xóa.
    }
};
