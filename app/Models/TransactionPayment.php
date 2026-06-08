<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id',
        'payment_method_id',
        'amount',
        'status',
        'proof_image',
        'proof_uploaded_at',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_response',
        'notes',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'proof_uploaded_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
