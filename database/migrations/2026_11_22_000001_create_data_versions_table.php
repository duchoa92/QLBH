<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Số phiên bản theo nhóm dữ liệu để các trang tự cập nhật. Xem App\Support\DataVersion. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_versions', function (Blueprint $table) {
            $table->string('channel', 64)->primary();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_versions');
    }
};
