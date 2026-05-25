<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'stock', 'min_stock', 'min_purchase', 'is_unlimited'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected $casts = [
        'is_unlimited' => 'boolean',
    ];
}
