<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAdClick extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_ad_id',
        'product_id',
        'user_id',
        'ip_address',
        'user_agent',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(ProductAd::class, 'product_ad_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
