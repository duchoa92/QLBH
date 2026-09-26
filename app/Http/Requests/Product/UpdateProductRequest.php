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
                'required',
                'exists:categories,id',
            ],

            'brand_id' => [
                'required',
                'exists:brands,id',
            ],

            'unit_id' => [
                'nullable',
                'required_if:has_unit_conversion,true',
                'exists:units,id',
            ],

            'has_unit_conversion' => ['boolean'],

            'conversion_unit_id' => [
                Rule::when(
                    $this->boolean('has_unit_conversion'),
                    ['required', 'different:unit_id', 'exists:units,id'],
                    ['nullable']
                ),
            ],

            'conversion_factor' => [
                Rule::when(
                    $this->boolean('has_unit_conversion'),
                    ['required', 'integer', 'min:2'],
                    ['nullable', 'integer', 'min:1']
                ),
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

            'manage_stock_by_serial' => 'boolean',

            'product_type' => [
                'nullable',
                'in:normal,imei,service,combo',
            ],

            'variants' => 'array',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.sell_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.imeis' => 'nullable|string',
            'variants.*.attributes' => 'array',
            'variants.*.attributes.*.name' => 'required|string',
            'variants.*.attributes.*.value' => 'nullable|array',
            'variants.*.attributes.*.value.*' => 'string',
        ];
    }
}
