<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'type' => 'required|in:manual,gateway',
            'bank_name' => 'nullable|string|max:255|required_if:type,manual',
            'account_number' => 'nullable|string|max:255|required_if:type,manual',
            'account_name' => 'nullable|string|max:255|required_if:type,manual',
            'api_key' => 'nullable|string|max:255|required_if:type,gateway',
            'api_secret' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:255',
            'webhook_token' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric|min:0',
        ];
    }
}
