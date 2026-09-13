<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourierRequest extends FormRequest
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
        $courierId = $this->route('courier')?->id ?? $this->route('courier');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('couriers', 'code')->ignore($courierId)->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ];
    }
}
