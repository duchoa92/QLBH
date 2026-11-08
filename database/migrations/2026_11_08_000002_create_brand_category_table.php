<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thương hiệu dùng chung: một thương hiệu có thể thuộc nhiều danh mục.
 *
 * - Tạo bảng trung gian brand_category.
 * - Chuyển liên kết cũ brands.category_id sang bảng mới.
 * - Gộp các thương hiệu trùng tên (không phân biệt hoa/thường) thành một bản ghi:
 *   giữ bản có id nhỏ nhất, chuyển sản phẩm và danh mục sang bản đó, xóa mềm các bản dư.
 * - Cột brands.category_id được GIỮ LẠI (không còn dùng) để có thể quay lui an toàn;
 *   có thể xóa ở một migration riêng sau khi đã chạy ổn định.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_category', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['brand_id', 'category_id']);
        });

        DB::transaction(function () {
            // 1) Liên kết cũ: mỗi thương hiệu một danh mục.
            DB::table('brands')
                ->whereNotNull('category_id')
                ->orderBy('id')
                ->get(['id', 'category_id'])
                ->each(fn ($brand) => DB::table('brand_category')->insertOrIgnore([
                    'brand_id' => $brand->id,
                    'category_id' => $brand->category_id,
                ]));

            // 2) Gộp thương hiệu trùng tên.
            DB::table('brands')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'name'])
                ->groupBy(fn ($brand) => mb_strtolower(trim($brand->name)))
                ->each(function ($rows) {
                    if ($rows->count() < 2) {
                        return;
                    }

                    $keeperId = $rows->first()->id;
                    $duplicateIds = $rows->slice(1)->pluck('id')->all();

                    DB::table('brand_category')
                        ->whereIn('brand_id', $duplicateIds)
                        ->pluck('category_id')
                        ->unique()
                        ->each(fn ($categoryId) => DB::table('brand_category')->insertOrIgnore([
                            'brand_id' => $keeperId,
                            'category_id' => $categoryId,
                        ]));

                    DB::table('brand_category')->whereIn('brand_id', $duplicateIds)->delete();
                    DB::table('products')->whereIn('brand_id', $duplicateIds)->update(['brand_id' => $keeperId]);
                    DB::table('brands')->whereIn('id', $duplicateIds)->update(['deleted_at' => now()]);
                });

            // 3) Sản phẩm đang dùng cặp (thương hiệu, danh mục) nào thì cặp đó phải được liên kết,
            //    để form sản phẩm vẫn hiện đúng thương hiệu sau khi chuyển đổi.
            DB::table('products')
                ->whereNotNull('brand_id')
                ->whereNotNull('category_id')
                ->distinct()
                ->get(['brand_id', 'category_id'])
                ->each(fn ($pair) => DB::table('brand_category')->insertOrIgnore([
                    'brand_id' => $pair->brand_id,
                    'category_id' => $pair->category_id,
                ]));
        });
    }

    public function down(): void
    {
        // Trả lại một danh mục cho mỗi thương hiệu (danh mục nhỏ nhất) rồi bỏ bảng trung gian.
        DB::statement(
            'UPDATE brands SET category_id = (SELECT MIN(category_id) FROM brand_category WHERE brand_category.brand_id = brands.id)'
        );

        Schema::dropIfExists('brand_category');
    }
};
