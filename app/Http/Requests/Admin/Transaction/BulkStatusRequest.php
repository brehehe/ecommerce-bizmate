<?php

namespace App\Http\Requests\Admin\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class BulkStatusRequest extends FormRequest
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
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|string|exists:transactions,id',
            'status' => 'required|string|in:belum_bayar,menunggu,diproses,dikemas,dikirim,selesai,batal',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
