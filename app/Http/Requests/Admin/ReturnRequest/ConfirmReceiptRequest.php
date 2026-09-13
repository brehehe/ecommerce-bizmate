<?php

namespace App\Http\Requests\Admin\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReceiptRequest extends FormRequest
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
            'stock_action' => 'nullable|string|in:active,damaged',
            'item_condition' => 'nullable|string|in:layak_jual,rusak',
            'notes_admin' => 'nullable|string|max:500',
        ];
    }
}
