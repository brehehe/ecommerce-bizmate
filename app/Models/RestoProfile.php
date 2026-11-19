<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestoProfile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'resto_name',
        'slug',
        'culinary_category',
        'description',
        'logo_path',
        'banner_path',
        'qris_image_path',
        'address',
        'latitude',
        'longitude',
        'operating_hours',
        'payment_methods',
        'delivery_fee_type',
        'delivery_fee_base',
        'delivery_free_min_order',
        'avg_prep_time_minutes',
        'allow_pickup',
        'allow_delivery',
        'max_delivery_radius_km',
        'is_open_now',
        'approval_status',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'payment_methods' => 'array',
        'delivery_fee_base' => 'decimal:2',
        'delivery_free_min_order' => 'decimal:2',
        'avg_prep_time_minutes' => 'integer',
        'allow_pickup' => 'boolean',
        'allow_delivery' => 'boolean',
        'max_delivery_radius_km' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_open_now' => 'boolean',
    ];

    /**
     * Get default payment methods configuration.
     *
     * @return array<string, mixed>
     */
    public function getDefaultPaymentMethods(): array
    {
        return [
            'cod' => [
                'is_active' => true,
                'label' => 'Bayar di Tempat (COD / Kasir)',
                'description' => 'Bayar tunai langsung di kasir resto saat ambil atau kepada kurir saat pesanan tiba.',
            ],
            'bank_transfer' => [
                'is_active' => true,
                'label' => 'Transfer Bank Resto',
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => $this->resto_name ?: 'Dapur Resto',
                'description' => 'Transfer ke rekening resmi resto dan lampirkan bukti pembayaran.',
            ],
            'qris' => [
                'is_active' => true,
                'label' => 'QRIS Resto (Semua E-Wallet / Bank)',
                'merchant_name' => $this->resto_name ?: 'Dapur Resto',
                'description' => 'Scan kode QRIS resto menggunakan GoPay, OVO, DANA, ShopeePay, atau Mobile Banking.',
                'image_path' => $this->qris_image_path,
            ],
            'waris' => [
                'is_active' => false,
                'label' => 'WARIS QRIS Dinamis Resto',
                'description' => 'Generate Dynamic QRIS real-time dengan verifikasi instan melalui Webhook portal WARIS.',
                'api_url' => 'https://analisis.giradia.id/api/v1',
                'api_key' => '',
                'webhook_secret' => '',
                'qris_code' => 'random',
                'admin_fee' => 0,
            ],
        ];
    }

    /**
     * Get active resolved payment methods.
     *
     * @return array<string, mixed>
     */
    public function getResolvedPaymentMethods(): array
    {
        $defaults = $this->getDefaultPaymentMethods();
        $stored = $this->payment_methods ?? [];

        $resolved = [];
        foreach ($defaults as $key => $defaultVal) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $item = $stored[$key];

                // Normalize 'active' to 'is_active'
                if (isset($item['active']) && ! isset($item['is_active'])) {
                    $item['is_active'] = filter_var($item['active'], FILTER_VALIDATE_BOOLEAN);
                } elseif (isset($item['is_active'])) {
                    $item['is_active'] = filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN);
                }

                // Normalize QRIS merchant name
                if ($key === 'qris' && isset($item['account_name']) && empty($item['merchant_name'])) {
                    $item['merchant_name'] = $item['account_name'];
                }

                $resolved[$key] = array_merge($defaultVal, $item);
            } else {
                $resolved[$key] = $defaultVal;
            }
        }

        return $resolved;
    }

    /**
     * Get default operating hours configuration.
     *
     * @return array<string, array{open: string, close: string, is_closed: bool}>
     */
    public function getDefaultOperatingHours(): array
    {
        return [
            'monday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
            'tuesday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
            'wednesday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
            'thursday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
            'friday' => ['open' => '09:00', 'close' => '21:00', 'is_closed' => false],
            'saturday' => ['open' => '09:00', 'close' => '22:00', 'is_closed' => false],
            'sunday' => ['open' => '09:00', 'close' => '22:00', 'is_closed' => false],
        ];
    }

    /**
     * Get resolved operating hours, converting legacy format if needed.
     *
     * @return array<string, array{open: string, close: string, is_closed: bool}>
     */
    public function getResolvedOperatingHours(): array
    {
        $defaults = $this->getDefaultOperatingHours();
        $stored = $this->operating_hours;

        if (! is_array($stored) || empty($stored)) {
            return $defaults;
        }

        $dayMap = [
            'senin' => 'monday',
            'selasa' => 'tuesday',
            'rabu' => 'wednesday',
            'kamis' => 'thursday',
            'jumat' => 'friday',
            'sabtu' => 'saturday',
            'minggu' => 'sunday',
        ];

        $resolved = $defaults;

        foreach ($stored as $key => $val) {
            $normalizedKey = strtolower((string) $key);
            $targetKey = $dayMap[$normalizedKey] ?? $normalizedKey;

            if (! isset($defaults[$targetKey])) {
                continue;
            }

            if (is_array($val)) {
                $resolved[$targetKey] = [
                    'open' => (string) ($val['open'] ?? '09:00'),
                    'close' => (string) ($val['close'] ?? '21:00'),
                    'is_closed' => (bool) ($val['is_closed'] ?? false),
                ];
            } elseif (is_string($val) && str_contains($val, '-')) {
                $parts = array_map('trim', explode('-', $val, 2));
                $resolved[$targetKey] = [
                    'open' => $parts[0] ?: '09:00',
                    'close' => $parts[1] ?: '21:00',
                    'is_closed' => false,
                ];
            }
        }

        return $resolved;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id', 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'resto_id', 'user_id');
    }

    /**
     * @return HasMany<StoreReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(StoreReview::class, 'resto_profile_id');
    }

    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->reviews()->where('is_public', true)->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function getReviewCountAttribute(): int
    {
        return $this->reviews()->where('is_public', true)->count();
    }
}
