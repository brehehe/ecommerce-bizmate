<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class SelectVouchersRequest extends FormRequest
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
            'voucher_belanja_id' => 'nullable|string|exists:promotions,id',
            'voucher_ongkir_id' => 'nullable|string|exists:promotions,id',
        ];
    }
}
