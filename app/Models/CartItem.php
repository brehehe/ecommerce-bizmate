<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'product_id', 'product_variant_id', 'quantity', 'is_checked'];

    protected $casts = [
        'is_checked' => 'boolean',
    ];

    protected $appends = [
        'unit_price',
        'subtotal',
    ];

    public function getUnitPriceAttribute(): float
    {
        if (array_key_exists('unit_price', $this->attributes) && ! is_null($this->attributes['unit_price'])) {
            return (float) $this->attributes['unit_price'];
        }

        if (array_key_exists('computed_price', $this->attributes) && ! is_null($this->attributes['computed_price'])) {
            return (float) $this->attributes['computed_price'];
        }

        $variant = $this->relationLoaded('productVariant') ? $this->productVariant : null;
        $product = $this->relationLoaded('product') ? $this->product : null;

        if (! $variant && ! $product && ($this->product_id || $this->product_variant_id)) {
            $variant = $this->productVariant;
            $product = $this->product;
        }

        if ($variant) {
            return (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0));
        }

        if ($product) {
            return (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));
        }

        return 0.0;
    }

    public function getSubtotalAttribute(): float
    {
        if (array_key_exists('subtotal', $this->attributes) && ! is_null($this->attributes['subtotal'])) {
            return (float) $this->attributes['subtotal'];
        }

        return (float) ($this->unit_price * $this->quantity);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
