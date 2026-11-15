<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_face_profiles', function (Blueprint $table): void {
            // enroll = mẫu đăng ký; auto = mẫu hệ thống tự thêm sau khi nhân viên xác nhận "Đúng" ở POS.
            $table->string('source', 10)->default('enroll')->after('descriptor');
        });
    }

    public function down(): void
    {
        Schema::table('customer_face_profiles', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
