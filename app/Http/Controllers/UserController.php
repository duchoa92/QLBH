<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Danh sách user
     */
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('roles:id,name')->get(),
        ]);
    }


    /* Sửa thông tin user */
    public function edit(User $user): \Inertia\Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles:id'),
            'roles' => Role::all(['id', 'name']),
        ]);
    }

    /* chập nhập thông tin user */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'role_id' => 'required|exists:roles,id',
        ]);

        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
        ]);

        // đồng bộ role (1 user = 1 role)
        $user->roles()->sync([$data['role_id']]);

        return redirect()->route('users.index');
    }


    /**
     * Form tạo user
     */
    
    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::all(['id', 'name']),
        ]);
    }

    /* cập nhập vào database */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = \App\Models\User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => bcrypt($data['password']),
        ]);

        $user->roles()->attach($data['role_id']);

        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
            // 1️⃣ Không được xoá chính mình
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Không thể xoá chính bạn');
        }

        // 2️⃣ Không được xoá admin
        if ($user->roles()->where('name', 'admin')->exists()) {
            return back()->with('error', 'Không thể xoá tài khoản Admin');
        }

        // 3️⃣ Không được xoá user cuối cùng
        if (User::count() <= 1) {
            return back()->with('error', 'Hệ thống phải có ít nhất 1 user');
        }

        // 4️⃣ Xoá role trước (tránh lỗi FK)
        $user->roles()->detach();

        // 5️⃣ Xoá user
        $user->delete();

        return back()->with('success', 'Đã xoá người dùng');
    }
}
