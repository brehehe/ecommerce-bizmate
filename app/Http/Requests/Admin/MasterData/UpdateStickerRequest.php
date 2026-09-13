<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStickerRequest extends FormRequest
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
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'is_active' => 'nullable|boolean',
        ];
    }
}
