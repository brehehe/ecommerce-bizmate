<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerAdWallet extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'balance',
        'total_spent',
        'total_topup',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_topup' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SellerAdTransaction::class, 'user_id', 'user_id');
    }

    /**
     * Get or create ad wallet for given user.
     */
    public static function getOrCreateForUser(string $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['balance' => 0, 'total_spent' => 0, 'total_topup' => 0]
        );
    }
}
