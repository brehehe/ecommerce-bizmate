<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductListingPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'product_id',
        'user_id',
        'amount',
        'days',
        'payment_method',
        'gateway_transaction_id',
        'gateway_response',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'days' => 'integer',
        'paid_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
