<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrackingRequest extends FormRequest
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
            'tracking_number' => 'nullable|string|max:100',
            'booking_code' => 'nullable|string|max:100',
            'courier_name' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
            'courier_user_id' => 'nullable|exists:users,id',
        ];
    }
}
