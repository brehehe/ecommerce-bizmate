<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.cost' => 'nullable|numeric|min:0',
            'products.*.variants' => 'nullable|array',
            'products.*.variants.*.id' => 'required|exists:product_variants,id',
            'products.*.variants.*.price' => 'nullable|numeric|min:0',
            'products.*.variants.*.cost' => 'nullable|numeric|min:0',
        ];
    }
}
