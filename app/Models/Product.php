<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'origin_address_id',
        'name',
        'slug',
        'sku',
        'category_id',
        'brand_id',
        'brand',
        'stock_status',
        'summary',
        'description',
        'specifications',
        'size_chart',
        'weight',
        'length',
        'width',
        'height',
        'tax_enabled',
        'tax_rate',
        'active',
        'is_digital',
        'image',
        'video_path',
        'is_exclusive',
        'exclusive_min_level_order',
        'is_early_access',
        'early_access_until',
        'early_access_min_level_order',
        'model_3d_path',
        'order',
        'condition',
        'listing_expires_at',
        'listing_fee',
        'listing_days',
    ];

    public static function isSellerModeEnabled(): bool
    {
        return (bool) config('app.is_seller', false);
    }

    public function scopeNotExpired($query)
    {
        // Rule: If IS_SELLER is false in .env / settings, listing_expires_at does NOT apply to ANY product (all are unlimited)!
        if (! self::isSellerModeEnabled()) {
            return $query;
        }

        $feeEnabled = filter_var(
            Setting::where('key', 'product_listing_fee_enabled')->value('value') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $feeEnabled) {
            return $query;
        }

        return $query->where(function ($q) {
            // Admin products (user_id is null OR owner user has is_seller = false)
            $q->where(function ($adminQ) {
                $adminQ->whereNull('user_id')
                    ->orWhereHas('user', function ($u) {
                        $u->where('is_seller', false);
                    });
            })
            // Seller products (user_id is not null AND owner user has is_seller = true) -> MUST be paid (listing_expires_at > now())
                ->orWhere(function ($sellerQ) {
                    $sellerQ->whereNotNull('user_id')
                        ->whereHas('user', function ($u) {
                            $u->where('is_seller', true);
                        })
                        ->whereNotNull('listing_expires_at')
                        ->where('listing_expires_at', '>', now());
                });
        });
    }

    public function scopeActiveAndNotExpired($query)
    {
        return $query->where('active', true)->notExpired();
    }

    public function isListingExpired(): bool
    {
        // Rule: If IS_SELLER is false, listing_expires_at does NOT apply!
        if (! self::isSellerModeEnabled()) {
            return false;
        }

        $feeEnabled = filter_var(
            Setting::where('key', 'product_listing_fee_enabled')->value('value') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $feeEnabled) {
            return false;
        }

        if ($this->user_id && $this->user && $this->user->is_seller) {
            if (! $this->listing_expires_at) {
                return true;
            }

            return $this->listing_expires_at->isPast();
        }

        return false;
    }

    public function remainingListingDays(): int
    {
        if (! $this->listing_expires_at) {
            return 999999;
        }

        if ($this->isListingExpired()) {
            return 0;
        }

        return (int) now()->diffInDays($this->listing_expires_at, false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function originAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'origin_address_id');
    }

    public function brandRelation()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function productPrice()
    {
        return $this->hasOne(ProductPrice::class)->whereNull('product_variant_id');
    }

    public function tierPrices()
    {
        return $this->hasMany(ProductTierPrice::class)->whereNull('product_variant_id')->orderBy('min_qty', 'asc');
    }

    public function productStock()
    {
        return $this->hasOne(ProductStock::class)->whereNull('product_variant_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order', 'asc');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class)->orderBy('sort_order', 'asc');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function promotionItems()
    {
        return $this->hasMany(PromotionItem::class);
    }

    protected $casts = [
        'active' => 'boolean',
        'is_digital' => 'boolean',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'decimal:2',
        'weight' => 'integer',
        'is_exclusive' => 'boolean',
        'is_early_access' => 'boolean',
        'early_access_until' => 'datetime',
        'exclusive_min_level_order' => 'integer',
        'early_access_min_level_order' => 'integer',
        'length' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'specifications' => 'array',
        'size_chart' => 'array',
        'order' => 'integer',
        'listing_expires_at' => 'datetime',
        'listing_fee' => 'decimal:2',
        'listing_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->order)) {
                $model->order = (int) static::max('order') + 1;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'product_brands');
    }

    /**
     * Transaction items for this product — used to calculate sold counts.
     */
    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Reviews for this product.
     */
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * Return items for this product.
     */
    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }
}
