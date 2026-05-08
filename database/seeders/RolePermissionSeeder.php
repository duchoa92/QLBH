<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Reset cache
        |--------------------------------------------------------------------------
        */

        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            'categories.view',
            'categories.create',
            'categories.edit',
            'categories.delete',

            /*
            |--------------------------------------------------------------------------
            | Products
            |--------------------------------------------------------------------------
            */

            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

        ];

        /*
        |--------------------------------------------------------------------------
        | Create permissions
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {

            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Give all permissions
        |--------------------------------------------------------------------------
        */

        $superAdminRole->syncPermissions(
            Permission::all()
        );
    }
}