<?php

namespace App\Http\Requests\Admin\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectReturnRequest extends FormRequest
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
            'notes_admin' => 'required|string|max:500',
        ];
    }
}
