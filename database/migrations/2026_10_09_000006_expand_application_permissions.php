<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissionNames = collect(config('permissions'))
            ->flatMap(fn (array $group) => array_keys($group))
            ->unique()
            ->values();

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $allPermissions = Permission::query()->where('guard_name', 'web')->get();
        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super Admin', 'Admin', 'admin'])
            ->get()
            ->each(fn (Role $role) => $role->syncPermissions($allPermissions));

        $technician = Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'Kỹ thuật viên')
            ->first();

        if ($technician && ! $technician->permissions()->exists()) {
            $technician->syncPermissions([
                'repairs.view',
                'repairs.create',
                'repairs.edit',
                'repairs.complete',
                'repairs.return',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission records may already be assigned to roles and users; keep them on rollback.
    }
};
