<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table): void {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Mã phiếu
            |--------------------------------------------------------------------------
            */

            $table->string('code')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Khách hàng
            |--------------------------------------------------------------------------
            */

            $table->string('customer_name');

            $table->string('customer_phone');

            $table->string('contact_phone')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Thiết bị
            |--------------------------------------------------------------------------
            */

            $table->string('device_name');

            $table->string('imei')
                ->nullable()
                ->index();

            $table->string('serial')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Mật khẩu
            |--------------------------------------------------------------------------
            */

            $table->string('screen_password')
                ->nullable();

            $table->text('screen_pattern')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tài khoản máy
            |--------------------------------------------------------------------------
            */

            $table->string('account_type')
                ->nullable();

            $table->string('account_email')
                ->nullable();

            $table->string('account_password')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tình trạng
            |--------------------------------------------------------------------------
            */

            $table->json('issue')
                ->nullable();

            $table->text('repair_request')
                ->nullable();

            $table->json('accessories')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Chi phí
            |--------------------------------------------------------------------------
            */

            $table->decimal('estimated_cost', 15, 2)
                ->nullable();

            $table->decimal('final_cost', 15, 2)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Trạng thái
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('pending')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Ghi chú
            |--------------------------------------------------------------------------
            */

            $table->longText('note')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Kỹ thuật viên
            |--------------------------------------------------------------------------
            */

            $table->foreignIdFor(User::class, 'technician_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Thời gian
            |--------------------------------------------------------------------------
            */

            $table->timestamp('received_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('returned_at')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};