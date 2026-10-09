<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        $target = $this->route('user');

        if ($target?->username === 'admin' || $target?->hasRole('Super Admin')) {
            return $user?->username === 'admin' && $user->hasRole('Super Admin');
        }

        return $user && $user->can('users.edit');
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $userId = $target->id;

        return [

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:255',

                Rule::unique('users', 'username')
                    ->ignore($userId),
                function (string $attribute, mixed $value, \Closure $fail) use ($target): void {
                    if ($target->hasRole('Super Admin') && $value !== 'admin') {
                        $fail('Tên đăng nhập của Super Admin luôn là admin.');
                    }
                },
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',

                Rule::unique('users', 'phone')
                    ->ignore($userId),
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',

                Rule::unique('users', 'email')
                    ->ignore($userId),
            ],

            'password' => [
                'nullable',
                'confirmed',
                Password::defaults(),
            ],

            'role' => [
                'required',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                function (string $attribute, mixed $value, \Closure $fail) use ($target): void {
                    $actor = auth()->user();
                    $isSuperAdmin = $actor?->username === 'admin' && $actor->hasRole('Super Admin');
                    $isProtectedAdmin = $target->username === 'admin' || $target->hasRole('Super Admin');

                    if ($isProtectedAdmin && ($value !== 'Super Admin' || ! $isSuperAdmin)) {
                        $fail('Tài khoản admin phải được giữ vai trò Super Admin.');
                    }

                    if ($value === 'Super Admin' && (! $isSuperAdmin || $this->input('username') !== 'admin')) {
                        $fail('Chỉ tài khoản admin được giữ vai trò Super Admin.');
                    }

                    if ($value === 'Admin' && ! $isSuperAdmin && ! $target->hasRole('Admin')) {
                        $fail('Chỉ Super Admin được gán vai trò Admin.');
                    }
                },
            ],
        ];
    }
}
