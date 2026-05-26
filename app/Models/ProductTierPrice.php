<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductTierPrice extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'min_qty', 'price'];

    protected $casts = [
        'min_qty' => 'integer',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
