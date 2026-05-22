<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepairRequest extends FormRequest
{
    /**
     * Authorize request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            'customer_name' => [
                'required',
                'string',
                'max:255',
            ],

            'customer_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            /*
            |--------------------------------------------------------------------------
            | Device
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Account
            |--------------------------------------------------------------------------
            */

            'account_type' => [
                'nullable',
                'string',
                'max:50',
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

            /*
            |--------------------------------------------------------------------------
            | Repair
            |--------------------------------------------------------------------------
            */

            'issue' => [
                'nullable',
                'array',
            ],

            'issue.*' => [
                'string',
                'max:255',
            ],

            'repair_request' => [
                'nullable',
                'string',
            ],

            'estimated_cost' => [
                'nullable',
            ],

            'accessories' => [
                'nullable',
                'array',
            ],

            'accessories.*' => [
                'string',
                'max:255',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Images
            |--------------------------------------------------------------------------
            */

            'images' => [
                'nullable',
                'array',
            ],

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }
}