<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class UploadProofRequest extends FormRequest
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
            'proof_image' => 'required_without:proof_of_payment|nullable|image|mimes:jpeg,png,jpg|max:2048',
            'proof_of_payment' => 'required_without:proof_image|nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
