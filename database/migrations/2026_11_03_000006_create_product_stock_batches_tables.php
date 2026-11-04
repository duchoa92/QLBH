<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('stock_import_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('remaining_quantity');
            // Giá theo một đơn vị tồn kho cơ sở, để trừ kho/quy đổi chính xác.
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->date('imported_at')->nullable();
            $table->boolean('is_opening_balance')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'variant_id', 'remaining_quantity'], 'stock_batches_available_index');
        });

        Schema::create('sale_item_stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_gift_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_stock_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->timestamps();
            $table->index('product_stock_batch_id');
        });

        // Tồn hiện hữu chưa có dữ liệu lô: tạo một lô số dư đầu kỳ để chuyển đổi
        // an toàn. Các phiếu nhập sau migration sẽ tạo lô riêng theo từng lần nhập.
        $now = now();
        $products = DB::table('products')
            ->whereNull('deleted_at')
            ->where('manage_stock_by_serial', false)
            ->where('stock', '>', 0)
            ->select('id', 'stock', 'cost_price', 'sell_price')
            ->get();

        foreach ($products as $product) {
            $hasVariants = DB::table('product_variants')
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->exists();
            if ($hasVariants) {
                continue;
            }
            DB::table('product_stock_batches')->insert([
                'product_id' => $product->id,
                'variant_id' => null,
                'initial_quantity' => $product->stock,
                'remaining_quantity' => $product->stock,
                'cost_price' => $product->cost_price ?? 0,
                'sell_price' => $product->sell_price ?? 0,
                'imported_at' => $now->toDateString(),
                'is_opening_balance' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('product_variants')->where('is_active', true)->where('stock', '>', 0)->get() as $variant) {
            DB::table('product_stock_batches')->insert([
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'initial_quantity' => $variant->stock,
                'remaining_quantity' => $variant->stock,
                'cost_price' => $variant->cost_price ?? 0,
                'sell_price' => $variant->sell_price ?? 0,
                'imported_at' => $now->toDateString(),
                'is_opening_balance' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_stock_batches');
        Schema::dropIfExists('product_stock_batches');
    }
};
