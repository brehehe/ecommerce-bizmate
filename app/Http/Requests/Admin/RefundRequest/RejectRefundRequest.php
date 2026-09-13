<?php

namespace App\Http\Requests\Admin\RefundRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectRefundRequest extends FormRequest
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
            'notes_admin' => 'nullable|string|max:500',
            'admin_notes' => 'nullable|string|max:500',
            'reject_reason' => 'nullable|string|max:500',
        ];
    }
}
