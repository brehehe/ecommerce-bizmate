<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSelfPickup = $this->input('courier') === 'self_pickup';

        return [
            'destination' => $isSelfPickup ? 'nullable|string' : 'required_without:address_id|nullable|string',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
            'is_international' => 'nullable|boolean',
            'address_id' => 'nullable|string',
        ];
    }
}
