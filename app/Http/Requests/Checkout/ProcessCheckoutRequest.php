<?php

namespace App\Http\Requests\Checkout;

use App\Services\MidtransService;
use Illuminate\Foundation\Http\FormRequest;

class ProcessCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null || (bool) config('app.is_checkout_without_login', false);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $fields = [
            'province_id', 'regency_id', 'district_id', 'village_id',
            'biteship_area_id', 'rajaongkir_destination_id', 'postal_code',
            'customer_address_id', 'resto_id', 'courier_id',
        ];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) !== null && ! is_array($this->input($field))) {
                $val = trim((string) $this->input($field));
                $merge[$field] = $val !== '' ? $val : null;
            }
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSelfPickup = $this->input('shipping_courier') === 'self_pickup';
        $isDigital = $this->input('shipping_courier') === 'digital';
        $isGuest = $this->user() === null;

        $rules = [
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
            'customer_address_id' => ($isSelfPickup || $isDigital || $isGuest) ? 'nullable' : 'required|exists:customer_addresses,id',
            'pickup_date' => 'nullable|string',
            'pickup_time' => 'nullable|string',
            'pickup_at' => 'nullable|string',
            'resto_id' => 'nullable|string',
            'fulfillment_type' => 'nullable|string|in:self_pickup,store_courier,dine_in',
            'pickup_type' => 'nullable|string|in:takeaway,dine_in,delivery',
            'table_number' => 'nullable|string|max:50',
            'scheduled_date' => 'nullable|string|max:50',
            'scheduled_time_slot' => 'nullable|string|max:50',
        ];

        if ($isGuest) {
            $rules['customer_name'] = 'required|string|max:100';
            $rules['customer_email'] = 'required|email|max:100';
            $rules['customer_phone'] = 'required|string|min:8|max:25';

            if (! $isSelfPickup && ! $isDigital) {
                $rules['full_address'] = 'required|string|max:500';
                $rules['province_name'] = 'required|string|max:100';
                $rules['regency_name'] = 'required|string|max:100';
                $rules['district_name'] = 'required|string|max:100';
                $rules['postal_code'] = 'required|string|max:20';
                $rules['province_id'] = 'nullable|string|max:50';
                $rules['regency_id'] = 'nullable|string|max:50';
                $rules['district_id'] = 'nullable|string|max:50';
                $rules['village_id'] = 'nullable|string|max:50';
                $rules['village_name'] = 'nullable|string|max:100';
                $rules['biteship_area_id'] = 'nullable|string|max:100';
                $rules['rajaongkir_destination_id'] = 'nullable|string|max:100';
                $rules['latitude'] = 'nullable|numeric';
                $rules['longitude'] = 'nullable|numeric';
                $rules['receiver_name'] = 'nullable|string|max:100';
                $rules['phone_number'] = 'nullable|string|max:25';
            }
        }

        return $rules;
    }
}
