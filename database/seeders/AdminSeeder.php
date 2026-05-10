<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Tạo quyền
        $permissions = [
            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
        ];

        foreach ($permissions as $permission) {

            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Tạo role admin
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        // Gán toàn bộ quyền cho admin
        $adminRole->syncPermissions(
            Permission::all()
        );

        // Tạo user admin
        $user = User::firstOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'name' => 'Administrator',
                'phone' => '0906064789',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('12345678'),
            ]
        );

        // Gán role admin
        $user->assignRole($adminRole);
    }
}