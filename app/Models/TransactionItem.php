<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'variant_name',
        'product_image',
        'quantity',
        'hpp',
        'harga_jual',
        'diskon_item',
        'harga_akhir',
        'subtotal',
        'is_gift_item',
        'gift_from_promotion',
        'applied_promotion_id',
        'promo_quantity_used',
        'note',
    ];

    protected $casts = [
        'hpp' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'diskon_item' => 'decimal:2',
        'harga_akhir' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'is_gift_item' => 'boolean',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
