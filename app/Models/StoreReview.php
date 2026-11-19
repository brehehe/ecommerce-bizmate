<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreReview extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'resto_profile_id',
        'seller_id',
        'user_id',
        'customer_name',
        'customer_phone',
        'rating',
        'comment',
        'reply',
        'is_public',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_public' => 'boolean',
    ];

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<RestoProfile, $this>
     */
    public function restoProfile(): BelongsTo
    {
        return $this->belongsTo(RestoProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
