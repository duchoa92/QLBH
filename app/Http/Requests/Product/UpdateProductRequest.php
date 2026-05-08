<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Authorize
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [

            'category_id' => [
                'nullable',
                'exists:categories,id',
            ],

            'brand_id' => [
                'nullable',
                'exists:brands,id',
            ],

            'unit_id' => [
                'nullable',
                'exists:units,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'sku' => [

                'required',

                'string',

                'max:255',

                Rule::unique(
                    'products',
                    'sku'
                )->ignore($productId),

            ],

            'barcode' => [

                'nullable',

                'string',

                Rule::unique(
                    'products',
                    'barcode'
                )->ignore($productId),

            ],

            'cost_price' => [
                'required',
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

            'alert_stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'has_imei' => [
                'boolean',
            ],

            'is_active' => [
                'boolean',
            ],

            'description' => [
                'nullable',
                'string',
            ],

        ];
    }
}