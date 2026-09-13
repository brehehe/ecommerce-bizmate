<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStocksRequest extends FormRequest
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
            'products.*.stock' => 'nullable|integer|min:0',
            'products.*.min_stock' => 'nullable|integer|min:0',
            'products.*.is_unlimited' => 'nullable|boolean',
            'products.*.variants' => 'nullable|array',
            'products.*.variants.*.id' => 'required|exists:product_variants,id',
            'products.*.variants.*.stock' => 'nullable|integer|min:0',
            'products.*.variants.*.min_stock' => 'nullable|integer|min:0',
            'products.*.variants.*.is_unlimited' => 'nullable|boolean',
        ];
    }
}
