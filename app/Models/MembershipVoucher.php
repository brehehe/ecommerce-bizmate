<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipVoucher extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'membership_level_id',
        'code',
        'label',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'trigger',
        'status',
        'valid_from',
        'valid_until',
        'used_at',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'used_at' => 'datetime',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'active'
            && now()->between($this->valid_from, $this->valid_until);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_until', '>=', now()->toDateString());
    }
}
