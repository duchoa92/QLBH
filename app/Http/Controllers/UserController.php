<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render(
            'Users/Index',
            [
                'users' => $this->service->paginate(),
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Users/Create',
            [
                'roles' => Role::query()
                    ->select('id', 'name')
                    ->get(),
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

        return Inertia::render(
            'Users/Edit',
            [
                'user' => $user,

                'roles' => Role::query()
                    ->select('id', 'name')
                    ->get(),
            ]
        );
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

        $this->service->delete($user);

        return redirect()
            ->back()
            ->with(
                'success',
                'Xóa thành công'
            );
    }
}