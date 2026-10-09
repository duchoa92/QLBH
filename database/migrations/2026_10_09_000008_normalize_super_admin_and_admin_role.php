<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $roles = Role::query()->where('guard_name', 'web')->get();
        $adminRole = $roles->first(fn (Role $role) => $role->name === 'Admin');
        $legacyAdminRole = $roles->first(fn (Role $role) => $role->name === 'admin');

        if (! $adminRole && $legacyAdminRole) {
            $legacyAdminRole->name = 'Admin';
            $legacyAdminRole->save();
            $adminRole = $legacyAdminRole;
        } elseif (! $adminRole) {
            $adminRole = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        } elseif ($legacyAdminRole && $legacyAdminRole->id !== $adminRole->id) {
            $legacyAdminRole->users->each(fn (User $user) => $user->assignRole($adminRole));
            $legacyAdminRole->delete();
        }

        $allPermissions = Permission::query()->where('guard_name', 'web')->get();
        $adminRole->syncPermissions($allPermissions->reject(fn (Permission $permission) => $permission->name === 'roles.manage'));

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions($allPermissions);

        $adminUser = User::query()->where('username', 'admin')->first();
        if (! $adminUser) {
            $adminUser = User::role('Super Admin')->orderBy('id')->first();
            if ($adminUser) {
                $adminUser->username = 'admin';
                $adminUser->save();
            }
        }

        if ($adminUser) {
            User::role('Super Admin')->whereKeyNot($adminUser->getKey())->get()
                ->each(fn (User $user) => $user->removeRole($superAdminRole));
            $adminUser->syncRoles([$superAdminRole]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep normalized account and permission assignments to avoid restoring duplicate admin access.
    }
};
