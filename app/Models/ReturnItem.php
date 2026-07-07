<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'return_items';

    protected $fillable = [
        'return_id',
        'transaction_item_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'quantity_returned',
        'unit_price',
        'refund_subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'refund_subtotal' => 'decimal:2',
        'quantity_returned' => 'integer',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }
}
