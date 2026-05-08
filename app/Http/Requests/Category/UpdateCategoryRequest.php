<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'parent_id' => [
                'nullable',
                'exists:categories,id'
            ],

            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'sort_order' => [
                'nullable',
                'integer'
            ],

            'is_active' => [
                'nullable',
                'boolean'
            ],

        ];
    }
}