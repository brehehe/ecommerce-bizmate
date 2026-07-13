<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'code',
        'discount_type',
        'discount_value',
        'min_purchase',
        'max_discount',
        'quota',
        'used_count',
        'start_time',
        'end_time',
        'is_active',
        'settings',
        'member_early_access_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'member_early_access_minutes' => 'integer',
        'discount_value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }
}
