<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'promotion_id',
        'product_id',
        'product_variant_id',
        'discount_type',
        'discount_value',
        'promo_price',
        'promo_stock',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'promo_price' => 'decimal:2',
        'promo_stock' => 'integer',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
