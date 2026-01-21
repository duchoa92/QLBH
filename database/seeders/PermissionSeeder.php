<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            /*
            |--------------------------------------------------------------------------
            | USER / ROLE
            |--------------------------------------------------------------------------
            */
            ['name' => 'users.view', 'group' => 'users', 'description' => 'Xem danh sách người dùng'],
            ['name' => 'users.create', 'group' => 'users', 'description' => 'Tạo người dùng'],
            ['name' => 'users.update', 'group' => 'users', 'description' => 'Cập nhật người dùng'],
            ['name' => 'users.delete', 'group' => 'users', 'description' => 'Xóa người dùng'],

            /*
            |--------------------------------------------------------------------------
            | PRODUCTS / STOCK
            |--------------------------------------------------------------------------
            */
            ['name' => 'products.view', 'group' => 'products', 'description' => 'Xem sản phẩm'],
            ['name' => 'products.create', 'group' => 'products', 'description' => 'Thêm sản phẩm'],
            ['name' => 'products.update', 'group' => 'products', 'description' => 'Cập nhật sản phẩm'],
            ['name' => 'products.delete', 'group' => 'products', 'description' => 'Xóa sản phẩm'],

            /*
            |--------------------------------------------------------------------------
            | ORDERS / SALES
            |--------------------------------------------------------------------------
            */
            ['name' => 'orders.create', 'group' => 'orders', 'description' => 'Tạo đơn bán hàng'],
            ['name' => 'orders.view', 'group' => 'orders', 'description' => 'Xem đơn bán hàng'],
            ['name' => 'orders.cancel', 'group' => 'orders', 'description' => 'Hủy đơn hàng'],

            /*
            |--------------------------------------------------------------------------
            | REPAIRS
            |--------------------------------------------------------------------------
            */
            ['name' => 'repairs.receive', 'group' => 'repairs', 'description' => 'Nhận máy sửa'],
            ['name' => 'repairs.update', 'group' => 'repairs', 'description' => 'Cập nhật sửa chữa'],
            ['name' => 'repairs.complete', 'group' => 'repairs', 'description' => 'Hoàn tất sửa chữa'],
            ['name' => 'repairs.view', 'group' => 'repairs', 'description' => 'Xem đơn sửa chữa'],

            /*
            |--------------------------------------------------------------------------
            | REPORTS
            |--------------------------------------------------------------------------
            */
            ['name' => 'reports.view', 'group' => 'reports', 'description' => 'Xem báo cáo'],
            ['name' => 'reports.export', 'group' => 'reports', 'description' => 'Xuất báo cáo'],

            /*
            |--------------------------------------------------------------------------
            | AI / ANALYTICS (AI-READY)
            |--------------------------------------------------------------------------
            */
            ['name' => 'ai.customer.predict', 'group' => 'ai', 'description' => 'Dự đoán hành vi khách hàng'],
            ['name' => 'ai.repair.detect', 'group' => 'ai', 'description' => 'Phát hiện lỗi sửa chữa'],
            ['name' => 'ai.stock.forecast', 'group' => 'ai', 'description' => 'Dự báo tồn kho'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}
