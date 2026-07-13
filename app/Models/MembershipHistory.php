<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipHistory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'from_level_id',
        'to_level_id',
        'action',
        'reason',
        'total_purchase_at_time',
        'total_transactions_at_time',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'total_purchase_at_time' => 'decimal:2',
            'total_transactions_at_time' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'from_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'to_level_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getActionLabel(): string
    {
        return match ($this->action) {
            'upgraded' => 'Naik Level',
            'downgraded' => 'Turun Level',
            'assigned' => 'Ditetapkan',
            'renewed' => 'Diperbarui',
            'expired' => 'Kedaluwarsa',
            default => $this->action,
        };
    }
}
