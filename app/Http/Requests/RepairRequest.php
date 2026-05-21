<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RepairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'customer_name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'device_name' => [
                'required',
                'string',
                'max:255',
            ],

            'imei' => [
                'nullable',
                'string',
                'max:255',
            ],

            'screen_password' => [
                'nullable',
                'string',
                'max:255',
            ],

            'screen_pattern' => [
                'nullable',
                'string',
            ],

            'account_type' => [
                'nullable',
                'string',
                'max:255',
            ],

            'account_email' => [
                'nullable',
                'string',
                'max:255',
            ],

            'account_password' => [
                'nullable',
                'string',
                'max:255',
            ],

            'issue' => [
                'nullable',
                'array',
            ],

            'accessories' => [
                'nullable',
                'array',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'images.*' => [
                'nullable',
                'image',
                'max:2048',
            ],
        ];
    }
}