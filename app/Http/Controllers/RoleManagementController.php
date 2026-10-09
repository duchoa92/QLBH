<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    private const PROTECTED_ROLES = ['Super Admin', 'Admin'];

    public function options()
    {
        $actor = request()->user();
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users_count,
                'system' => in_array($role->name, self::PROTECTED_ROLES, true),
                'read_only' => $role->name === 'Super Admin',
                'can_edit' => $role->name === 'Super Admin'
                    ? false
                    : ($role->name === 'Admin'
                        ? ($actor?->username === 'admin' && $actor->hasRole('Super Admin'))
                        : ($actor?->can('roles.manage') ?? false)),
            ]);

        return response()->json([
            'roles' => $roles,
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[\pL\pN _.-]+$/u',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        abort_if(in_array(mb_strtolower(trim($data['name'])), ['admin', 'super admin'], true), 422, 'Tên vai trò này đã được dành riêng cho hệ thống.');

        $role = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);
        $role->syncPermissions(collect($data['permissions'] ?? [])->reject(fn (string $permission) => $permission === 'roles.manage'));

        return back()->with('success', 'Đã tạo vai trò và lưu quyền thành công.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'Super Admin', 403, 'Không thể sửa vai trò Super Admin.');

        if ($role->name === 'Admin') {
            abort_unless($request->user()?->username === 'admin' && $request->user()->hasRole('Super Admin'), 403, 'Chỉ Super Admin mới được sửa quyền của vai trò Admin.');
        }

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[\pL\pN _.-]+$/u',
                $role->name === 'Admin' ? Rule::in(['Admin']) : function (string $attribute, mixed $value, \Closure $fail): void {
                    if (in_array(mb_strtolower(trim($value)), ['admin', 'super admin'], true)) {
                        $fail('Tên vai trò này đã được dành riêng cho hệ thống.');
                    }
                },
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);

        $role->update(['name' => trim($data['name'])]);
        $role->syncPermissions(collect($data['permissions'] ?? [])->reject(fn (string $permission) => $permission === 'roles.manage'));

        return back()->with('success', 'Đã cập nhật vai trò và quyền.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES, true), 403, 'Không thể xóa vai trò hệ thống.');

        if ($role->users()->exists()) {
            return back()->with('error', 'Không thể xóa vai trò đang được gán cho nhân viên. Hãy chuyển nhân viên sang vai trò khác trước.');
        }

        $role->delete();

        return back()->with('success', 'Đã xóa vai trò.');
    }
}
