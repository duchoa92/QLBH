<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            // Quầy POS đã phục vụ lượt này thì đánh dấu để các quầy khác không gợi ý lại.
            $table->timestamp('served_at')->nullable()->after('last_seen_at');
            $table->foreignId('served_by')->nullable()->after('served_at')->constrained('users')->nullOnDelete();
            // POS hỏi "ai đang ở cửa" liên tục: lọc theo last_seen_at.
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_visits', function (Blueprint $table): void {
            $table->dropIndex(['last_seen_at']);
            $table->dropConstrainedForeignId('served_by');
            $table->dropColumn('served_at');
        });
    }
};
