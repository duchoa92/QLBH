<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'exists:brands,id',
            ],

            'name' => [
                'required',
                'max:255',
            ],

            'sku' => [
                'nullable',
                'max:100',

                Rule::unique(
                    'products',
                    'sku'
                )->ignore($this->product),
            ],

            'barcode' => [
                'nullable',
                'max:100',

                Rule::unique(
                    'products',
                    'barcode'
                )->ignore($this->product),
            ],

            'imeis' => [
                'nullable',
                'string'
            ],

            'cost_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'sell_price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'description' => [
                'nullable',
            ],

            'is_active' => [
                'boolean',
            ],

            'image' => [
                'nullable',
                'image',
                'max:2048',
            ],
        ];
    }
}