<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $edit = Permission::findByName('sales.edit', 'web');
        $view = Permission::findByName('sales.view', 'web');

        User::query()
            ->whereHas('permissions', fn ($query) => $query->whereKey($view->id))
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo($edit));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep permission assignments intact if the migration is rolled back.
    }
};
