<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
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
            'status' => 'required|string|in:belum_bayar,menunggu,diproses,dikemas,out_for_pickup,dikirim,selesai,batal',
            'cancel_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
