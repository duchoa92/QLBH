<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            $table->string('code')->unique();

            $table->string('full_name');
            $table->string('search_text')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();

            $table->date('birthday')->nullable();

            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            $table->string('cccd')->nullable()->index();

            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('address')->nullable();

            // thống kê nhanh (CACHE)
            $table->decimal('point_balance', 15, 2)->default(0);
            $table->decimal('debt_balance', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);

            $table->timestamp('last_order_at')->nullable();

            $table->enum('customer_type', ['retail', 'vip', 'wholesaler'])
                ->default('retail');

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};