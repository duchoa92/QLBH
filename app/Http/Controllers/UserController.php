<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $service
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $request->merge(['search' => $search]);

        return Inertia::render(
            'Users/Index',
            [
                'users' => $this->service->paginate(),
                'filters' => ['search' => $search],
                'roles' => $this->roleData(),
                'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
                'can_assign_super_admin' => auth()->user()?->username === 'admin' && auth()->user()?->hasRole('Super Admin'),
                'can_manage_roles' => auth()->user()?->can('roles.manage') ?? false,
                'can_create_users' => auth()->user()?->can('users.create') ?? false,
                'can_edit_users' => auth()->user()?->can('users.edit') ?? false,
                'can_delete_users' => auth()->user()?->can('users.delete') ?? false,
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Users/Create',
            [
                'roles' => $this->roleData()->filter(fn (array $role) => ! $role['system'] || $this->canAssignProtectedRole())->values(),
            ]
        );
    }

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {

        $this->service->create(
            $request->validated()
        );

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Thêm user thành công'
            );
    }

    public function edit(User $user): Response
    {
        $user->load('roles');
        $existingRoleIds = $user->roles->pluck('id')->all();

        return Inertia::render(
            'Users/Edit',
            [
                'user' => $user,

                'roles' => $this->roleData()->filter(fn (array $role) => ! $role['system'] || $this->canAssignProtectedRole() || in_array($role['id'], $existingRoleIds, true))->values(),
            ]
        );
    }

    private function roleData()
    {
        return Role::query()
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
                'system' => in_array($role->name, ['Super Admin', 'Admin'], true),
                'read_only' => $role->name === 'Super Admin',
                'can_edit' => $role->name === 'Super Admin'
                    ? false
                    : ($role->name === 'Admin'
                        ? (auth()->user()?->username === 'admin' && auth()->user()?->hasRole('Super Admin'))
                        : (auth()->user()?->can('roles.manage') ?? false)),
            ]);
    }

    private function canAssignProtectedRole(): bool
    {
        $actor = auth()->user();

        return $actor?->username === 'admin' && $actor->hasRole('Super Admin');
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {

        $this->service->update(
            $user,
            $request->validated()
        );

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Cập nhật thành công'
            );
    }

    public function destroy(
        User $user
    ): RedirectResponse {

        abort_if($user->username === 'admin' || $user->hasRole('Super Admin'), 403, 'Không thể xóa tài khoản Super Admin chính.');

        $this->service->delete($user);

        return redirect()
            ->back()
            ->with(
                'success',
                'Xóa thành công'
            );
    }
}
