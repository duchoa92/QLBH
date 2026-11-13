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
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Khách hàng
            |--------------------------------------------------------------------------
            */

            $table->string('contact_phone')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Thiết bị
            |--------------------------------------------------------------------------
            */

            $table->string('device_name');
            $table->string('model', 100)->nullable();

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

            $table->text('screen_password')->nullable();

            $table->text('screen_pattern')
                ->nullable();
            $table->string('screen_lock_type', 20)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tài khoản máy
            |--------------------------------------------------------------------------
            */

            $table->string('account_type')
                ->nullable();

            $table->string('account_email')
                ->nullable();

            $table->text('account_password')->nullable();

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
            $table->decimal('parts_total', 15, 2)->default(0);
            $table->decimal('labor_cost', 15, 2)->default(0);
            $table->decimal('surcharge', 15, 2)->default(0);

            $table->decimal('final_cost', 15, 2)
                ->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->text('payment_note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Trạng thái
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('pending')
                ->index();
            $table->string('intake_type')->default('repair');
            $table->string('warranty_source_type')->nullable();
            $table->unsignedBigInteger('warranty_source_id')->nullable();
            $table->timestamp('warranty_expires_at')->nullable();
            $table->string('warranty_status')->nullable();
            $table->timestamp('warranty_declined_at')->nullable();
            $table->text('warranty_decline_reason')->nullable();
            $table->decimal('warranty_covered_amount', 15, 2)->default(0);

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
            $table->unsignedInteger('repair_warranty_days')->default(0);
            $table->timestamp('repair_warranty_started_at')->nullable();
            $table->timestamp('repair_warranty_expires_at')->nullable();
            $table->timestamp('repair_warranty_voided_at')->nullable();
            $table->text('repair_warranty_void_reason')->nullable();
            $table->foreignId('repair_warranty_voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
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