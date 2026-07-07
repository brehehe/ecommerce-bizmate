<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoinHistory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'amount',
        'type',
        'description',
        'order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->order)) {
                $model->order = static::withTrashed()->max('order') + 1;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
