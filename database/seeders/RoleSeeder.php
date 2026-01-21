<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'description' => 'Quản trị hệ thống'
            ],
            [
                'name' => 'manager',
                'description' => 'Quản lý cửa hàng'
            ],
            [
                'name' => 'staff',
                'description' => 'Nhân viên'
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
