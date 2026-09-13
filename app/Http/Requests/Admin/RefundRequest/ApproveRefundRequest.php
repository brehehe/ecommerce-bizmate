<?php

namespace App\Http\Requests\Admin\RefundRequest;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRefundRequest extends FormRequest
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
            'refund_method' => 'nullable|string|in:transfer_manual,points,loyalty_points',
            'admin_notes' => 'nullable|string|max:500',
        ];
    }
}
