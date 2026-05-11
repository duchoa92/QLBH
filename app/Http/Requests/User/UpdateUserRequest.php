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
        return auth()->user()->can('users.update');
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

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
                'exists:roles,name',
            ],
        ];
    }
}