<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingRequest extends FormRequest
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
            'products.*.weight' => 'nullable|integer|min:0',
            'products.*.length' => 'nullable|integer|min:0',
            'products.*.width' => 'nullable|integer|min:0',
            'products.*.height' => 'nullable|integer|min:0',
        ];
    }
}
