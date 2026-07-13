<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMembership extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'membership_level_id',
        'total_purchase',
        'total_transactions',
        'total_products',
        'total_points',
        'total_cashback',
        'joined_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total_purchase' => 'decimal:2',
            'total_transactions' => 'integer',
            'total_products' => 'integer',
            'total_points' => 'integer',
            'total_cashback' => 'decimal:2',
            'joined_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isExpired();
    }
}
