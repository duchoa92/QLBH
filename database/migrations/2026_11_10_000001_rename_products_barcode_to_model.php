<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cột products.barcode được dùng để lưu "Model" của sản phẩm => đổi tên thành products.model.
 * Dữ liệu cũ giữ nguyên. Model không phải mã định danh duy nhất (nhiều sản phẩm có thể cùng model)
 * nên bỏ ràng buộc unique, chỉ giữ chỉ mục thường để tìm kiếm.
 * (Cột product_variants.barcode không thay đổi.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'barcode') || Schema::hasColumn('products', 'model')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_barcode_unique');
            $table->dropIndex('products_barcode_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('barcode', 'model');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('model', 'products_model_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'model') || Schema::hasColumn('products', 'barcode')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_model_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('model', 'barcode');
        });

        // Không khôi phục ràng buộc unique vì dữ liệu hiện tại có thể đã có model trùng nhau.
        Schema::table('products', function (Blueprint $table) {
            $table->index('barcode', 'products_barcode_index');
        });
    }
};
