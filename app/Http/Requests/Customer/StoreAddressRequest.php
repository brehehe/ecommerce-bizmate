<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'label' => 'required|string|max:30',
            'receiver_name' => 'required|string|max:50',
            'phone_number' => 'required|string|max:15',
            'full_address' => 'required|string|max:200',
            'province_id' => 'nullable|string',
            'province_name' => 'nullable|string',
            'regency_id' => 'nullable|string',
            'regency_name' => 'nullable|string',
            'district_id' => 'nullable|string',
            'district_name' => 'nullable|string',
            'village_id' => 'nullable|string',
            'village_name' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'biteship_area_id' => 'nullable|string|max:100',
            'rajaongkir_destination_id' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'note' => 'nullable|string|max:45',
            'is_primary' => 'nullable|boolean',
        ];
    }
}
