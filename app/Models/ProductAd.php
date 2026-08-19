<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAd extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'product_id',
        'ad_type',
        'bid_per_click',
        'daily_budget',
        'spent_today',
        'total_spent',
        'impressions_count',
        'clicks_count',
        'status',
        'show_badge',
        'placements',
        'start_date',
        'end_date',
        'last_spent_reset_at',
    ];

    protected $casts = [
        'bid_per_click' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'spent_today' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'impressions_count' => 'integer',
        'clicks_count' => 'integer',
        'show_badge' => 'boolean',
        'placements' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_spent_reset_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ProductAdClick::class);
    }

    /**
     * Scope for active ads.
     */
    public function scopeActive($query)
    {
        $today = Carbon::today()->toDateString();

        return $query->where('status', 'active')
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
    }

    /**
     * Check if ad daily budget has reached limit and reset if next day.
     */
    public function checkAndResetDailyBudget(): void
    {
        $today = Carbon::today()->toDateString();
        if ($this->last_spent_reset_at !== $today) {
            $this->spent_today = 0;
            $this->last_spent_reset_at = $today;
            if ($this->status === 'depleted') {
                $this->status = 'active';
            }
            $this->save();
        }
    }
}
