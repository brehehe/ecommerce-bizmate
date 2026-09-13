<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCartRequest extends FormRequest
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
            'is_checked' => 'required|boolean',
            'ids' => 'nullable|array',
            'ids.*' => 'string|exists:cart_items,id',
        ];
    }
}
