<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'category_id',
        'brand',
        'stock_status',
        'summary',
        'description',
        'weight',
        'length',
        'width',
        'height',
        'tax_enabled',
        'tax_rate',
        'active',
        'image',
    ];

    public function productPrice()
    {
        return $this->hasOne(ProductPrice::class)->whereNull('product_variant_id');
    }

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->whereNull('product_variant_id')->orderBy('min_qty', 'asc');
    }

    public function productStock()
    {
        return $this->hasOne(ProductStock::class)->whereNull('product_variant_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function promotionItems()
    {
        return $this->hasMany(PromotionItem::class);
    }

    protected $casts = [
        'active' => 'boolean',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:2',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
