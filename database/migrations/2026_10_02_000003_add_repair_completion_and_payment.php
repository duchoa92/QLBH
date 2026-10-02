<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->decimal('parts_total', 15, 2)->default(0)->after('estimated_cost');
            $table->decimal('labor_cost', 15, 2)->default(0)->after('parts_total');
            $table->decimal('surcharge', 15, 2)->default(0)->after('labor_cost');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('final_cost');
            $table->decimal('change_amount', 15, 2)->default(0)->after('paid_amount');
            $table->string('payment_method')->nullable()->after('change_amount');
            $table->text('payment_note')->nullable()->after('payment_method');
        });

        Schema::create('repair_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->boolean('waiting_for_parts')->default(false)->after('parts_needed');
        });

        DB::table('repairs')->whereIn('status', ['checking', 'waiting_parts'])->update(['status' => 'repairing']);
        DB::table('repair_timelines')->whereIn('status', ['checking', 'waiting_parts'])->update(['status' => 'repairing']);
    }

    public function down(): void
    {
        Schema::table('repair_timelines', function (Blueprint $table): void {
            $table->dropColumn('waiting_for_parts');
        });
        Schema::dropIfExists('repair_parts');
        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropColumn(['parts_total', 'labor_cost', 'surcharge', 'paid_amount', 'change_amount', 'payment_method', 'payment_note']);
        });
    }
};
