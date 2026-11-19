<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $fields = ['destination', 'dest_area_id', 'biteship_area_id', 'resto_id', 'address_id'];

        foreach ($fields as $field) {
            if ($this->has($field) && $this->input($field) !== null && ! is_array($this->input($field))) {
                $val = trim((string) $this->input($field));
                $merge[$field] = $val !== '' ? $val : null;
            }
        }

        $courier = $this->input('courier');
        if ($courier === 'self_pickup') {
            $merge['destination'] = 'self_pickup';
            $merge['dest_area_id'] = null;
            $merge['biteship_area_id'] = null;
            $merge['address_id'] = null;
        } elseif ($courier === 'store_courier' && empty($merge['destination'] ?? $this->input('destination'))) {
            $merge['destination'] = 'store_courier';
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
        $courier = $this->input('courier');
        $isExemptFromDestination = in_array($courier, ['self_pickup', 'store_courier']);

        return [
            'destination' => $isExemptFromDestination ? 'nullable|string' : 'required_without:address_id|nullable|string',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
            'resto_id' => 'nullable|string',
            'is_international' => 'nullable|boolean',
            'address_id' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'dest_area_id' => 'nullable|string',
            'biteship_area_id' => 'nullable|string',
        ];
    }
}
