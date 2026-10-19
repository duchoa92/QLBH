<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $view = Permission::firstOrCreate(['name' => 'expenses.view', 'guard_name' => 'web']);
        $manage = Permission::firstOrCreate(['name' => 'expenses.manage', 'guard_name' => 'web']);
        Role::query()->where('guard_name', 'web')->whereIn('name', ['Super Admin', 'Admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$view, $manage]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve permission assignments on rollback.
    }
};
