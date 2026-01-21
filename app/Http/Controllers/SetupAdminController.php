<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class SetupAdminController extends Controller
{
    public function index()
    {
        // Nếu đã có user → không cho vào setup nữa
        if (User::count() > 0) {
            abort(404);
        }

        return inertia('Auth/SetupAdmin');
    }

    public function store(Request $request)
    {
        // An toàn: chặn nếu đã có user
        if (User::count() > 0) {
            abort(403, 'Admin đã được khởi tạo');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|min:6|confirmed',
        ]);

        // 1️⃣ Tạo user admin đầu tiên
        $user = User::create([
            'username' => 'admin', // admin mặc định
            'name' => $request->name,
            'password' => Hash::make($request->password),
            'status' => 1,
        ]);

        // 2️⃣ Tạo role admin nếu chưa có
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
        ]);

        // 3️⃣ Gán role admin
        $user->roles()->attach($adminRole->id);

        // 4️⃣ ĐĂNG NHẬP LUÔN (QUAN TRỌNG)
        Auth::login($user);

        // 5️⃣ Chuyển thẳng vào dashboard
        return redirect()->route('dashboard');
    }
}
