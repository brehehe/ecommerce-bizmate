<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipCashback extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'membership_level_id',
        'amount',
        'status',
        'description',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'credited_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCredited($query)
    {
        return $query->where('status', 'credited');
    }
}
