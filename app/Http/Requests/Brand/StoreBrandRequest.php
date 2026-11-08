<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission đã check ở route middleware
    }

    public function rules(): array
    {
        return [
            // Thương hiệu dùng chung nên tên là duy nhất trên toàn hệ thống.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name'),
            ],
            'category_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'category_ids.*' => [
                'integer',
                'distinct',
                'exists:categories,id',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Vui lòng nhập tên thương hiệu',
            'name.unique'            => 'Tên thương hiệu đã tồn tại (có thể đang nằm trong thùng rác)',
            'category_ids.required'  => 'Vui lòng chọn ít nhất một danh mục',
            'category_ids.min'       => 'Vui lòng chọn ít nhất một danh mục',
            'category_ids.*.exists'  => 'Danh mục không hợp lệ',
        ];
    }
}
