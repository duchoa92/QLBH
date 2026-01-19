<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // Thông tin cơ bản
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Tài chính
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->decimal('total_debt', 15, 2)->default(0);

            // Phân loại
            $table->enum('customer_type', ['new', 'regular', 'vip'])->default('new');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
