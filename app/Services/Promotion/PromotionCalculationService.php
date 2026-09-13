<?php

namespace App\Services\Promotion;

use App\Models\Product;
use App\Models\ProductAd;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\TransactionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PromotionCalculationService
{
    private ?array $promoItemsCache = null;

    private ?array $soldPromoQuantitiesCache = null;

    /**
     * Get active promotions cached for 30 seconds to minimize DB load on high traffic.
     *
     * @return Collection<int, Promotion>
     */
    public function getActivePromotions(): Collection
    {
        return Cache::remember('storefront_active_promotions', 30, function () {
            return Promotion::with(['items'])
                ->where('is_active', true)
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->get();
        });
    }

    /**
     * Get active non-voucher promotions (for automatic storefront & checkout calculations).
     *
     * @return Collection<int, Promotion>
     */
    public function getActiveNonVoucherPromotions(): Collection
    {
        return Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->whereNotIn('type', ['voucher_gratis_ongkir', 'voucher_belanja'])
            ->where(function ($q) {
                $q->whereNull('code')
                    ->orWhere('code', '')
                    ->orWhere('type', '!=', 'promo_toko');
            })
            ->get();
    }

    /**
     * Get map of active product ads cached.
     *
     * @return Collection<string, ProductAd>
     */
    public function getActiveAdsMap(): Collection
    {
        return Cache::remember('active_promoted_ads_map', 15, function () {
            return ProductAd::active()
                ->whereHas('user.adWallet', function ($q) {
                    $q->where('balance', '>', 0);
                })
                ->get()
                ->keyBy('product_id');
        });
    }

    /**
     * Populate bundling product details for promotions.
     *
     * @param  Collection<int, Promotion>  $promotions
     */
    public function populateBundlingProducts(Collection $promotions): void
    {
        $bundlingProductIds = [];
        foreach ($promotions as $promo) {
            if ($promo->type === 'bundling_gift' && isset($promo->settings['bundle'])) {
                $bundle = $promo->settings['bundle'];
                if (isset($bundle['buy_items'])) {
                    foreach ($bundle['buy_items'] as $buyItem) {
                        if (! empty($buyItem['product_id'])) {
                            $bundlingProductIds[] = $buyItem['product_id'];
                        }
                    }
                }
                if (isset($bundle['get_items'])) {
                    foreach ($bundle['get_items'] as $getItem) {
                        if (! empty($getItem['product_id'])) {
                            $bundlingProductIds[] = $getItem['product_id'];
                        }
                    }
                }
            }
        }
        $bundlingProductIds = array_unique($bundlingProductIds);

        if (empty($bundlingProductIds)) {
            return;
        }

        $productsMap = Product::with(['productPrice', 'images'])
            ->whereIn('id', $bundlingProductIds)
            ->get()
            ->keyBy('id');

        $promotions->each(function ($promo) use ($productsMap) {
            if ($promo->type === 'bundling_gift' && isset($promo->settings['bundle'])) {
                $bundle = $promo->settings['bundle'];

                if (isset($bundle['buy_items'])) {
                    foreach ($bundle['buy_items'] as &$buyItem) {
                        if (! empty($buyItem['product_id'])) {
                            $prod = $productsMap->get($buyItem['product_id']);
                            if ($prod) {
                                $buyItem['product_name'] = $prod->name;
                                $buyItem['product_slug'] = $prod->slug;
                                $buyItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $buyItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                if (isset($bundle['get_items'])) {
                    foreach ($bundle['get_items'] as &$getItem) {
                        if (! empty($getItem['product_id'])) {
                            $prod = $productsMap->get($getItem['product_id']);
                            if ($prod) {
                                $getItem['product_name'] = $prod->name;
                                $getItem['product_slug'] = $prod->slug;
                                $getItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $getItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                $promo->settings = array_merge($promo->settings, ['bundle' => $bundle]);
            }
        });
    }

    /**
     * Get remaining promo stock for a promotion item.
     */
    public function getRemainingPromoStock($promotionId, $productId, $variantId = null): ?int
    {
        if ($this->promoItemsCache === null) {
            $this->promoItemsCache = [];

            $activePromoIds = Promotion::where('is_active', true)
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->pluck('id');

            $promoItems = PromotionItem::whereIn('promotion_id', $activePromoIds)->get();
            foreach ($promoItems as $item) {
                $vId = $item->product_variant_id ?? 'null';
                $this->promoItemsCache["{$item->promotion_id}_{$item->product_id}_{$vId}"] = $item;
            }
        }

        if ($this->soldPromoQuantitiesCache === null) {
            $this->soldPromoQuantitiesCache = [];

            $soldQuantities = TransactionItem::selectRaw('applied_promotion_id, product_id, product_variant_id, SUM(promo_quantity_used) as total_used')
                ->whereIn('applied_promotion_id', array_keys($this->promoItemsCache ? array_flip(array_column($this->promoItemsCache, 'promotion_id')) : []))
                ->whereHas('transaction', function ($q) {
                    $q->where('status', '!=', 'batal');
                })
                ->groupBy('applied_promotion_id', 'product_id', 'product_variant_id')
                ->get();

            foreach ($soldQuantities as $item) {
                $vId = $item->product_variant_id ?? 'null';
                $this->soldPromoQuantitiesCache["{$item->applied_promotion_id}_{$item->product_id}_{$vId}"] = (int) $item->total_used;
            }
        }

        $vKey = $variantId ?? 'null';
        $item = $this->promoItemsCache["{$promotionId}_{$productId}_{$vKey}"] ?? null;
        if (! $item || is_null($item->promo_stock)) {
            return null;
        }

        $sold = $this->soldPromoQuantitiesCache["{$promotionId}_{$productId}_{$vKey}"] ?? 0;

        return max(0, (int) $item->promo_stock - $sold);
    }

    /**
     * Apply active promotions on a product and its variants with split pricing support.
     */
    public function applyPromotionsToProduct(Product $product, ?Collection $activePromotions = null, int $quantity = 1): void
    {
        $activePromotions ??= $this->getActivePromotions();
        $basePrice = (float) ($product->productPrice?->price ?? 0);

        // 1. Flash Sale
        $flashSalePromo = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'flash_sale') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }

            return $promo->items->contains(fn ($i) => $i->product_id === $product->id);
        });

        // 2. Bundling Gift
        $isBundlingActive = $activePromotions->contains(function ($promo) use ($product) {
            if ($promo->type !== 'bundling_gift') {
                return false;
            }
            $bundle = $promo->settings['bundle'] ?? null;
            if (! $bundle || empty($bundle['buy_items'])) {
                return false;
            }
            foreach ($bundle['buy_items'] as $bi) {
                if ($bi['product_id'] == $product->id) {
                    return true;
                }
            }

            return false;
        });

        // 3. Promo Produk
        $promoProduk = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'promo_produk') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }

            return $promo->items->contains(fn ($i) => $i->product_id === $product->id);
        });

        // 4. Promo Toko / Special Deals
        $promoToko = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'promo_toko' && $promo->type !== 'special_deals') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }

            return $promo->items->contains(fn ($i) => $i->product_id === $product->id);
        });

        $appliedPromo = null;
        $appliedItem = null;
        $remainingStock = null;

        $product->promo_rule = null;
        $product->applied_promotion_id = null;
        $product->promo_quantity_used = null;

        // 1. Try Flash Sale
        if ($flashSalePromo) {
            $fsRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, null);
            if (! is_null($fsRemainingStock)) {
                $product->promo_rule = [
                    'id' => $flashSalePromo->id,
                    'name' => $flashSalePromo->name,
                    'type' => $flashSalePromo->type,
                    'min_qty' => 1,
                    'remaining_promo_stock' => $fsRemainingStock,
                ];
            }

            if (is_null($fsRemainingStock) || $fsRemainingStock > 0) {
                $appliedPromo = $flashSalePromo;
                $remainingStock = $fsRemainingStock;
                if ($appliedPromo->items->isNotEmpty()) {
                    $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }
            }
        }

        // 2. Bundling check
        if (! $appliedPromo && $isBundlingActive) {
            $appliedPromo = null;
        }

        // 3. Try Promo Produk
        if (! $appliedPromo && ! $isBundlingActive && $promoProduk) {
            $minQty = $promoProduk->settings['min_qty'] ?? 1;
            $pItem = null;
            if ($promoProduk->items->isNotEmpty()) {
                $pItem = $promoProduk->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
            }

            $resolvedDiscountType = $pItem ? ($pItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
            $resolvedDiscountValue = $pItem ? ($pItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
            $resolvedPromoPrice = $pItem?->promo_price;

            $ppRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, null);

            $product->promo_rule = [
                'id' => $promoProduk->id,
                'name' => $promoProduk->name,
                'type' => $promoProduk->type,
                'min_qty' => (int) $minQty,
                'discount_type' => $resolvedDiscountType,
                'discount_value' => $resolvedDiscountValue,
                'promo_price' => $resolvedPromoPrice ? (float) $resolvedPromoPrice : null,
                'remaining_promo_stock' => $ppRemainingStock,
            ];

            if ($quantity >= $minQty && (is_null($ppRemainingStock) || $ppRemainingStock > 0)) {
                $appliedPromo = $promoProduk;
                $appliedItem = $pItem;
                $remainingStock = $ppRemainingStock;
            }
        }

        // 4. Try Promo Toko / Special Deals
        if (! $appliedPromo && ! $isBundlingActive && $promoToko) {
            $appliedPromo = $promoToko;
            if ($appliedPromo->items->isNotEmpty()) {
                $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
            }
        }

        if ($appliedPromo) {
            $discountType = $appliedItem ? ($appliedItem->discount_type ?? $appliedPromo->discount_type) : $appliedPromo->discount_type;
            $discountValue = $appliedItem ? ($appliedItem->discount_value ?? $appliedPromo->discount_value) : $appliedPromo->discount_value;
            $promoPrice = $appliedItem?->promo_price;

            if ($promoPrice && $promoPrice > 0) {
                $rawPromoPrice = (float) $promoPrice;
            } else {
                if ($discountType === 'percentage') {
                    $rawPromoPrice = $basePrice - ($basePrice * ($discountValue / 100));
                } elseif ($discountType === 'fixed') {
                    $rawPromoPrice = $basePrice - $discountValue;
                } else {
                    $rawPromoPrice = $basePrice;
                }
            }
            $rawPromoPrice = max(0, $rawPromoPrice);

            $promoQtyUsed = $quantity;
            $finalPrice = $rawPromoPrice;

            if (isset($remainingStock) && ! is_null($remainingStock)) {
                if ($remainingStock <= 0) {
                    $appliedPromo = null;
                    $finalPrice = $basePrice;
                    $promoQtyUsed = 0;
                } elseif ($remainingStock < $quantity) {
                    $promoQtyUsed = $remainingStock;
                    $normalQty = $quantity - $promoQtyUsed;
                    $finalPrice = (($promoQtyUsed * $rawPromoPrice) + ($normalQty * $basePrice)) / $quantity;
                }
            }

            if ($appliedPromo && $finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
                $product->promo_type = $appliedPromo->type;
                $product->promo_end_time = $appliedPromo->end_time?->toIso8601String();
                $product->keep_tier_prices = $appliedPromo->settings['keep_tier_prices'] ?? false;
                $product->applied_promotion_id = $appliedPromo->id;
                $product->promo_quantity_used = $promoQtyUsed;
                $product->discount_percentage = $basePrice > 0 ? round((($basePrice - $finalPrice) / $basePrice) * 100) : 0;
            } else {
                $product->is_promo = false;
                $product->promo_price = $basePrice;
                $product->original_price = $basePrice;
                $product->discount_percentage = 0;
                $product->promo_type = null;
                $product->promo_end_time = null;
                $product->keep_tier_prices = false;
            }
        } else {
            $product->is_promo = false;
            $product->promo_price = $basePrice;
            $product->original_price = $basePrice;
            $product->discount_percentage = 0;
            $product->promo_type = null;
            $product->promo_end_time = null;
            $product->keep_tier_prices = false;
        }

        // Apply to variants if loaded
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $vPrice = (float) ($variant->productPrice?->price ?? 0);
                $vAppliedPromo = null;
                $vAppliedItem = null;
                $variant->promo_rule = null;
                $variant->applied_promotion_id = null;
                $variant->promo_quantity_used = null;
                $vRemainingStock = null;

                if ($flashSalePromo) {
                    $fsRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, $variant->id);
                    if (! is_null($fsRemainingStock)) {
                        $variant->promo_rule = [
                            'id' => $flashSalePromo->id,
                            'name' => $flashSalePromo->name,
                            'type' => $flashSalePromo->type,
                            'min_qty' => 1,
                            'remaining_promo_stock' => $fsRemainingStock,
                        ];
                    }

                    if (is_null($fsRemainingStock) || $fsRemainingStock > 0) {
                        $vAppliedPromo = $flashSalePromo;
                        $vRemainingStock = $fsRemainingStock;
                        if ($vAppliedPromo->items->isNotEmpty()) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                            }) ?? $vAppliedPromo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                    }
                }

                if (! $vAppliedPromo && $isBundlingActive) {
                    $vAppliedPromo = null;
                }

                if (! $vAppliedPromo && ! $isBundlingActive && $promoProduk) {
                    $minQty = $promoProduk->settings['min_qty'] ?? 1;
                    $ppRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, $variant->id);
                    $vItem = null;
                    if ($promoProduk->items->isNotEmpty()) {
                        $vItem = $promoProduk->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        }) ?? $promoProduk->items->first(function ($i) use ($product) {
                            return $i->product_id === $product->id && is_null($i->product_variant_id);
                        });
                    }

                    $vResolvedDiscountType = $vItem ? ($vItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                    $vResolvedDiscountValue = $vItem ? ($vItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                    $vResolvedPromoPrice = $vItem?->promo_price;

                    $variant->promo_rule = [
                        'id' => $promoProduk->id,
                        'name' => $promoProduk->name,
                        'type' => $promoProduk->type,
                        'min_qty' => (int) $minQty,
                        'discount_type' => $vResolvedDiscountType,
                        'discount_value' => $vResolvedDiscountValue,
                        'promo_price' => $vResolvedPromoPrice ? (float) $vResolvedPromoPrice : null,
                        'remaining_promo_stock' => $ppRemainingStock,
                    ];

                    if ($quantity >= $minQty && (is_null($ppRemainingStock) || $ppRemainingStock > 0)) {
                        $vAppliedPromo = $promoProduk;
                        $vAppliedItem = $vItem;
                        $vRemainingStock = $ppRemainingStock;
                    }
                }

                if (! $vAppliedPromo && ! $isBundlingActive && $promoToko) {
                    $vAppliedPromo = $promoToko;
                    if ($vAppliedPromo->items->isNotEmpty()) {
                        $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        }) ?? $vAppliedPromo->items->first(function ($i) use ($product) {
                            return $i->product_id === $product->id && is_null($i->product_variant_id);
                        });
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vRawPromoPrice = (float) $vPromoPrice;
                    } else {
                        if ($vDiscountType === 'percentage') {
                            $vRawPromoPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                        } elseif ($vDiscountType === 'fixed') {
                            $vRawPromoPrice = $vPrice - $vDiscountValue;
                        } else {
                            $vRawPromoPrice = $vPrice;
                        }
                    }
                    $vRawPromoPrice = max(0, $vRawPromoPrice);

                    $vPromoQtyUsed = $quantity;
                    $vFinalPrice = $vRawPromoPrice;

                    if (isset($vRemainingStock) && ! is_null($vRemainingStock)) {
                        if ($vRemainingStock <= 0) {
                            $vAppliedPromo = null;
                            $vFinalPrice = $vPrice;
                            $vPromoQtyUsed = 0;
                        } elseif ($vRemainingStock < $quantity) {
                            $vPromoQtyUsed = $vRemainingStock;
                            $vNormalQty = $quantity - $vPromoQtyUsed;
                            $vFinalPrice = (($vPromoQtyUsed * $vRawPromoPrice) + ($vNormalQty * $vPrice)) / $quantity;
                        }
                    }

                    if ($vAppliedPromo && $vFinalPrice < $vPrice) {
                        $variant->is_promo = true;
                        $variant->promo_price = $vFinalPrice;
                        $variant->original_price = $vPrice;
                        $variant->promo_type = $vAppliedPromo->type;
                        $variant->promo_end_time = $vAppliedPromo->end_time?->toIso8601String();
                        $variant->keep_tier_prices = $vAppliedPromo->settings['keep_tier_prices'] ?? false;
                        $variant->applied_promotion_id = $vAppliedPromo->id;
                        $variant->promo_quantity_used = $vPromoQtyUsed;
                        $variant->discount_percentage = $vPrice > 0 ? round((($vPrice - $vFinalPrice) / $vPrice) * 100) : 0;
                    } else {
                        $variant->is_promo = false;
                        $variant->promo_price = $vPrice;
                        $variant->original_price = $vPrice;
                        $variant->discount_percentage = 0;
                        $variant->promo_type = null;
                        $variant->promo_end_time = null;
                        $variant->keep_tier_prices = false;
                    }
                } else {
                    $variant->is_promo = false;
                    $variant->promo_price = $vPrice;
                    $variant->original_price = $vPrice;
                    $variant->discount_percentage = 0;
                    $variant->promo_type = null;
                    $variant->promo_end_time = null;
                    $variant->keep_tier_prices = false;
                }
            }
        }
    }
}
