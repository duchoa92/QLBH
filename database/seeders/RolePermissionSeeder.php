<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::where('name', 'admin')->first();
        $manager = Role::where('name', 'manager')->first();
        $staff = Role::where('name', 'staff')->first();

        // =======================
        // ADMIN: FULL PERMISSION
        // =======================
        $admin->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        // =======================
        // MANAGER
        // =======================
        $managerPermissions = Permission::whereIn('group', [
            'products',
            'orders',
            'repairs',
            'reports',
            'users',
        ])->pluck('id')->toArray();

        $manager->permissions()->sync($managerPermissions);

        // =======================
        // STAFF
        // =======================
        $staffPermissions = Permission::whereIn('name', [
            // Products
            'products.view',

            // Orders
            'orders.create',
            'orders.view',

            // Repairs
            'repairs.receive',
            'repairs.update',
            'repairs.view',
        ])->pluck('id')->toArray();

        $staff->permissions()->sync($staffPermissions);
    }
}
