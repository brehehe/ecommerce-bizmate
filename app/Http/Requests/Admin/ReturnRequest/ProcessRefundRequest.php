<?php

namespace App\Http\Requests\Admin\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
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
            'refund_amount' => 'nullable|numeric|min:0',
            'notes_admin' => 'nullable|string|max:500',
        ];
    }
}
