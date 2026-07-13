<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipBenefit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'membership_level_id',
        'type',
        'label',
        'description',
        'value',
        'icon',
        'is_active',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'discount_percentage' => 'Diskon Persentase',
            'discount_nominal' => 'Diskon Nominal',
            'free_shipping' => 'Gratis Ongkir',
            'cashback_percentage' => 'Cashback Persentase',
            'cashback_nominal' => 'Cashback Nominal',
            'point_multiplier' => 'Multiplier Poin',
            'auto_voucher' => 'Voucher Otomatis',
            'birthday_bonus' => 'Bonus Ulang Tahun',
            'flash_sale_access' => 'Akses Flash Sale Khusus',
            'priority_cs' => 'Prioritas Customer Service',
            'exclusive_product' => 'Produk Eksklusif',
            'early_access' => 'Early Access Produk Baru',
            default => $this->type,
        };
    }
}
