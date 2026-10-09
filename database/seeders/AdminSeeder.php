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

        // Vai trò Admin dùng cho quản trị vận hành, không có quyền thay đổi vai trò.
        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions(Permission::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'roles.manage')
            ->get());

        // Tạo user admin
        $user = User::firstOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'name' => 'Administrator',
                'phone' => '0906064789',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('111111'),
            ]
        );

        // Tài khoản duy nhất được giữ vai trò Super Admin là username admin.
        $user->syncRoles('Super Admin');
    }
}
