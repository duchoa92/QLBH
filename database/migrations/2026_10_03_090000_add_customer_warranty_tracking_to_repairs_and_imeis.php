<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imeis', function (Blueprint $table): void {
            $table->timestamp('customer_warranty_expires_at')->nullable()->after('warranty_expired_at');
            $table->timestamp('customer_warranty_voided_at')->nullable()->after('customer_warranty_expires_at');
            $table->text('customer_warranty_void_reason')->nullable()->after('customer_warranty_voided_at');
            $table->foreignId('customer_warranty_voided_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('repairs', function (Blueprint $table): void {
            $table->string('intake_type')->default('repair')->after('status');
            $table->string('warranty_source_type')->nullable()->after('intake_type');
            $table->unsignedBigInteger('warranty_source_id')->nullable()->after('warranty_source_type');
            $table->timestamp('warranty_expires_at')->nullable()->after('warranty_source_id');
            $table->string('warranty_status')->nullable()->after('warranty_expires_at');
            $table->timestamp('warranty_declined_at')->nullable()->after('warranty_status');
            $table->text('warranty_decline_reason')->nullable()->after('warranty_declined_at');
            $table->decimal('warranty_covered_amount', 15, 2)->default(0)->after('warranty_decline_reason');

            $table->unsignedInteger('repair_warranty_days')->default(0)->after('returned_at');
            $table->timestamp('repair_warranty_started_at')->nullable()->after('repair_warranty_days');
            $table->timestamp('repair_warranty_expires_at')->nullable()->after('repair_warranty_started_at');
            $table->timestamp('repair_warranty_voided_at')->nullable()->after('repair_warranty_expires_at');
            $table->text('repair_warranty_void_reason')->nullable()->after('repair_warranty_voided_at');
            $table->foreignId('repair_warranty_voided_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropForeign(['repair_warranty_voided_by']);
            $table->dropColumn([
                'intake_type', 'warranty_source_type', 'warranty_source_id', 'warranty_expires_at',
                'warranty_status', 'warranty_declined_at', 'warranty_decline_reason',
                'warranty_covered_amount',
                'repair_warranty_days', 'repair_warranty_started_at', 'repair_warranty_expires_at',
                'repair_warranty_voided_at', 'repair_warranty_void_reason', 'repair_warranty_voided_by',
            ]);
        });

        Schema::table('product_imeis', function (Blueprint $table): void {
            $table->dropForeign(['customer_warranty_voided_by']);
            $table->dropColumn([
                'customer_warranty_expires_at', 'customer_warranty_voided_at',
                'customer_warranty_void_reason', 'customer_warranty_voided_by',
            ]);
        });
    }
};
