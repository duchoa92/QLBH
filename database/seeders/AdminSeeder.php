<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate([
            'name' => 'admin'
        ]);

        $user = User::updateOrCreate(
            [
                'username' => 'admin'
            ],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'phone' => '0906064789',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('111111')
            ]
        );

        $user->assignRole($adminRole);
    }
}