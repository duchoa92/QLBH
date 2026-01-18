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
        // Đã có user → cấm
        if (User::count() > 0) {
            return redirect('/');
        }

        return inertia('Auth/SetupAdmin');
    }

 /*    public function create()
    {
        // Nếu đã có user → không cho vào lại
        if (User::count() > 0) {
            return redirect('/login');
        }

        return inertia('Auth/SetupAdmin');
    } */

    public function store(Request $request)
    {
        if (User::count() > 0) {
            return redirect('/login');
        }

        $request->validate([
        /*     'name' => 'required',
            'password' => 'required|min:6|confirmed', */
            'name' => 'required|string|max:255',
            'password' => 'required|min:6|confirmed',
            'email' => 'nullable|email|unique:users',
            'phone' => 'nullable|unique:users',
        ]);

        $user=User::create([
            'username' => 'admin',
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'status' => 1,
        ]);

         // Tạo role admin nếu chưa có
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Gán role
        $user->roles()->attach($adminRole->id);

        return redirect('/login')->with('success', 'Tạo Admin thành công');
    }
}

