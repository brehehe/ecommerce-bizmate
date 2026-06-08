<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'category_id',
        'brand_id',
        'brand',
        'stock_status',
        'summary',
        'description',
        'specifications',
        'size_chart',
        'weight',
        'length',
        'width',
        'height',
        'tax_enabled',
        'tax_rate',
        'active',
        'is_digital',
        'image',
        'video_path',
        'model_3d_path',
        'model_3d_usdz_path',
    ];

    public function brandRelation()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

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
        return $this->hasMany(ProductImage::class)->orderBy('sort_order', 'asc');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class)->orderBy('sort_order', 'asc');
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
        'is_digital' => 'boolean',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:2',
        'weight' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'specifications' => 'array',
        'size_chart' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'product_brands');
    }

    /**
     * Transaction items for this product — used to calculate sold counts.
     */
    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Reviews for this product.
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
}
