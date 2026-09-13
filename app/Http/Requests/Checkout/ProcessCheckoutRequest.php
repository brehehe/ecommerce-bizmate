<?php

namespace App\Http\Requests\Checkout;

use App\Services\MidtransService;
use Illuminate\Foundation\Http\FormRequest;

class ProcessCheckoutRequest extends FormRequest
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
        $isSelfPickup = $this->input('shipping_courier') === 'self_pickup';

        return [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'midtrans_payment_type_key' => 'nullable|string|in:'.implode(',', array_keys(MidtransService::$paymentTypes)),
            'courier_id' => 'nullable|exists:couriers,id',
            'shipping_courier' => 'required|string|max:50',
            'shipping_service' => 'required|string|max:50',
            'shipping_etd' => 'nullable|string|max:50',
            'shipping_fee' => 'required|numeric|min:0',
            'voucher_code' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'use_coins' => 'nullable|boolean',
            'item_notes' => 'nullable|array',
            'customer_address_id' => $isSelfPickup ? 'nullable|exists:customer_addresses,id' : 'nullable|exists:customer_addresses,id',
        ];
    }
}
