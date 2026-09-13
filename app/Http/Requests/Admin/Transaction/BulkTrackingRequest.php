<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class BulkTrackingRequest extends FormRequest
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
            'tracking_data' => 'required|array|min:1',
            'tracking_data.*.id' => 'required|string|exists:transactions,id',
            'tracking_data.*.tracking_number' => 'required|string|max:100',
            'tracking_data.*.courier_name' => 'nullable|string|max:100',
        ];
    }
}
