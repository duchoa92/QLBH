<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SetupAdminController extends Controller
{
     public function index()
    {
        return inertia('Auth/SetupAdmin');
    }

    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'username' => 'admin', // ÉP CỨNG
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'status' => 1,
        ]);

        // Tạo role admin nếu chưa có
        $adminRole = Role::firstOrCreate([
            'name' => 'admin'
        ]);

        // Gán role
        $user->roles()->attach($adminRole->id);
        return redirect('/login')->with('success', 'Khởi tạo Admin thành công');
    }
}

