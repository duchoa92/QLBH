<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brand = $this->route('brand');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->ignore($brand),
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
