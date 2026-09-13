<?php

namespace App\Http\Requests\Admin\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('category_id') && ! $this->has('category_ids')) {
            $this->merge(['category_ids' => [$this->input('category_id')]]);
        }
        if ($this->has('brand_id') && ! $this->has('brand_ids')) {
            $this->merge(['brand_ids' => array_filter([$this->input('brand_id')])]);
        }
        if ($this->has('user_id') && (! $this->input('user_id') || ! Str::isUuid($this->input('user_id')))) {
            $this->merge(['user_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'brand' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'is_unlimited' => 'boolean',
            'is_digital' => 'boolean',
            'is_exclusive' => 'boolean',
            'exclusive_min_level_order' => 'nullable|integer|min:0',
            'is_early_access' => 'boolean',
            'early_access_until' => 'nullable|date',
            'early_access_min_level_order' => 'nullable|integer|min:0',
            'stock_status' => 'nullable|string',
            'condition' => 'nullable|string|in:new,used,second,rent',
            'summary' => 'nullable|string|max:255',
            'description' => 'required|string',
            'specifications' => 'nullable|array',
            'size_chart' => 'nullable|array',
            'weight' => 'nullable|integer|min:0',
            'length' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'photos' => 'nullable|array',
            'variations' => 'nullable|array',
            'variants' => 'nullable|array',
            'tier_prices' => 'nullable|array',
            'tier_prices.*.min_qty' => 'required|integer|min:2',
            'tier_prices.*.price' => 'required|numeric|min:0',
            'video_url' => 'nullable|string',
            'video_file' => 'nullable|file|mimes:mp4,mov,webm,qt|max:10240',
            'model_3d_url' => 'nullable|string',
            'model_3d_file' => 'nullable|file|max:10240',
            'model_3d_usdz_url' => 'nullable|string',
            'model_3d_usdz_file' => 'nullable|file|max:10240',
            'user_id' => 'nullable|string|exists:users,id',
            'new_seller_name' => 'nullable|string|max:255',
            'new_seller_phone' => 'nullable|string|max:50',
            'new_seller_email' => 'nullable|string|max:255',
            'new_seller_password' => 'nullable|string|min:6',
        ];
    }
}
