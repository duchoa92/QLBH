<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Gán các máy đã bán trước đây cho khách đã mua (lần bán gần nhất của mỗi IMEI, có khách, hóa đơn chưa hủy).
     * Chỉ thêm khi máy chưa có chủ trong customer_devices nên không ghi đè dữ liệu đã có.
     */
    public function up(): void
    {
        $latest = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'cancelled')
            ->whereNotNull('sales.customer_id')
            ->whereNotNull('sale_items.product_imei_id')
            ->groupBy('sale_items.product_imei_id')
            ->selectRaw('sale_items.product_imei_id, MAX(sale_items.id) as sale_item_id');

        DB::table('sale_items as si')
            ->joinSub($latest, 'latest', 'latest.sale_item_id', '=', 'si.id')
            ->join('sales', 'sales.id', '=', 'si.sale_id')
            ->join('product_imeis', 'product_imeis.id', '=', 'si.product_imei_id')
            ->join('products', 'products.id', '=', 'si.product_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('product_imeis.status', 'sold')
            ->select([
                'sales.customer_id', 'brands.name as brand', 'products.name as model',
                'product_imeis.imei', 'product_imeis.serial', 'product_imeis.color',
            ])
            ->orderBy('si.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $code = $row->imei ?: $row->serial;
                    if (! $code) {
                        continue;
                    }

                    $exists = DB::table('customer_devices')
                        ->where($row->imei ? 'imei' : 'serial', $code)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('customer_devices')->insert([
                        'customer_id' => $row->customer_id,
                        'brand' => $row->brand,
                        'model' => $row->model,
                        'imei' => $row->imei,
                        'serial' => $row->serial,
                        'color' => $row->color,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Không xóa dữ liệu đã gán.
    }
};
