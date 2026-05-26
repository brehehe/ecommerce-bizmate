<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'image', 'weight', 'length', 'width', 'height'];

    public function productPrice()
    {
        return $this->hasOne(ProductPrice::class);
    }

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('min_qty', 'asc');
    }

    public function productStock()
    {
        return $this->hasOne(ProductStock::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function options()
    {
        return $this->belongsToMany(ProductVariationOption::class, 'product_variant_option_combinations');
    }

    public function promotionItems()
    {
        return $this->hasMany(PromotionItem::class);
    }
}
