<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();

        return $user && $user->can('users.create');
    }

    public function rules(): array
    {
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
                'unique:users,username',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,phone',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],

            'role' => [
                'required',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $actor = auth()->user();
                    $isSuperAdmin = $actor?->username === 'admin' && $actor->hasRole('Super Admin');

                    if ($value === 'Super Admin' && (! $isSuperAdmin || $this->input('username') !== 'admin')) {
                        $fail('Chỉ tài khoản admin được giữ vai trò Super Admin.');
                    }

                    if ($this->input('username') === 'admin' && $value !== 'Super Admin') {
                        $fail('Tên đăng nhập admin phải thuộc vai trò Super Admin.');
                    }

                    if ($value === 'Admin' && ! $isSuperAdmin) {
                        $fail('Chỉ Super Admin được gán vai trò Admin.');
                    }
                },
            ],
        ];
    }
}
