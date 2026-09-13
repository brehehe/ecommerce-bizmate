<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductRequest extends FormRequest
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
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:products,id',
            'orders.*.order' => 'required|integer|min:0',
        ];
    }
}
