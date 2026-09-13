<?php

namespace App\Http\Requests\Admin\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;

class ProcessReplacementRequest extends FormRequest
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
            'replacement_tracking_number' => 'nullable|string|max:100',
            'replacement_courier_name' => 'nullable|string|max:100',
            'notes_admin' => 'nullable|string|max:500',
        ];
    }
}
