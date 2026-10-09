<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'payment_accounts.manage',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Admin', 'admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep access-control data on rollback so assigned permissions are never removed silently.
    }
};
