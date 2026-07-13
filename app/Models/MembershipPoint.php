<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPoint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'amount',
        'type',
        'description',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expires_at' => 'date',
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

    public function scopeEarned($query)
    {
        return $query->where('type', 'earned');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()->toDateString());
        });
    }
}
