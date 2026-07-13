<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipLevel extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'badge_color',
        'description',
        'order',
        'is_active',
        'min_total_purchase',
        'min_total_transactions',
        'min_total_products',
        'period_type',
        'auto_upgrade',
        'auto_downgrade',
        'apply_discount_at_checkout',
        'validity_months',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'auto_upgrade' => 'boolean',
            'auto_downgrade' => 'boolean',
            'apply_discount_at_checkout' => 'boolean',
            'min_total_purchase' => 'decimal:2',
            'min_total_transactions' => 'integer',
            'min_total_products' => 'integer',
            'validity_months' => 'integer',
        ];
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(MembershipBenefit::class)->orderBy('order');
    }

    public function activeBenefits(): HasMany
    {
        return $this->hasMany(MembershipBenefit::class)->where('is_active', true)->orderBy('order');
    }

    public function customerMemberships(): HasMany
    {
        return $this->hasMany(CustomerMembership::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MembershipHistory::class, 'to_level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
