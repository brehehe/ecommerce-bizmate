<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait AppliesProductPricing
 *
 * Shared pricing logic for storefront controllers that need to apply
 * promotions and membership discounts to products.
 */
trait AppliesProductPricing
{
    private ?array $promoItemsCache = null;

    private ?array $soldPromoQuantitiesCache = null;

    private float $memberDiscountPct = 0;

    /**
     * Get active promotions cached for 30 seconds to minimize DB load on high traffic.
     */
    private function getActivePromotions()
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
     * Initialize membership discount for the current user.
     * Call once at the start of any storefront method that renders products.
     */
    private function initMembership(): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        /** @var MembershipService $membershipService */
        $membershipService = app(MembershipService::class);
        $this->memberDiscountPct = $membershipService->getMembershipDiscountForUser($user);
    }

    /**
     * Apply membership discount fields to a product.
     * Sets member_price and member_discount_pct without overriding existing promo.
     */
    private function applyMembershipToProduct(Product $product): void
    {
        if ($this->memberDiscountPct <= 0) {
            $product->member_discount_pct = 0;
            $product->member_price = null;

            return;
        }

        $basePrice = $product->productPrice?->price ?? 0;
        $memberPrice = round($basePrice * (1 - $this->memberDiscountPct / 100), 2);

        $product->member_discount_pct = $this->memberDiscountPct;
        $product->member_price = $memberPrice;

        // Apply to variants too
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $vPrice = $variant->productPrice?->price ?? 0;
                $variant->member_price = round($vPrice * (1 - $this->memberDiscountPct / 100), 2);
                $variant->member_discount_pct = $this->memberDiscountPct;
            }
        }
    }

    /**
     * Retrieve sold quantity totals for a list of product IDs from completed transactions.
     *
     * @param  array<string>  $productIds
     * @return array<string, int> Map of product_id => total sold quantity
     */
    private function getSoldCountsForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->whereIn('transaction_items.product_id', $productIds)
            ->where('transactions.status', 'selesai')
            ->groupBy('transaction_items.product_id')
            ->selectRaw('transaction_items.product_id, SUM(transaction_items.quantity) as total_sold')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->product_id] = (int) $row->total_sold;
        }

        return $map;
    }

    /**
     * Get remaining promo stock for a promotion and product/variant.
     */
    private function getRemainingPromoStock($promotionId, $productId, $variantId = null): ?int
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

            $soldQuantities = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->selectRaw('transaction_items.applied_promotion_id, transaction_items.product_id, transaction_items.product_variant_id, SUM(transaction_items.promo_quantity_used) as total_used')
                ->whereIn('transaction_items.applied_promotion_id', array_keys($this->promoItemsCache ? array_flip(array_column($this->promoItemsCache, 'promotion_id')) : []))
                ->where('transactions.status', '!=', 'batal')
                ->groupBy('transaction_items.applied_promotion_id', 'transaction_items.product_id', 'transaction_items.product_variant_id')
                ->get();

            foreach ($soldQuantities as $item) {
                $vId = $item->product_variant_id ?? 'null';
                $this->soldPromoQuantitiesCache["{$item->applied_promotion_id}_{$item->product_id}_{$vId}"] = (int) $item->total_used;
            }
        }

        $vKey = $variantId ?? 'null';
        $cacheKey = "{$promotionId}_{$productId}_{$vKey}";
        $promoItem = $this->promoItemsCache[$cacheKey] ?? null;

        if (! $promoItem && $variantId) {
            $fallbackKey = "{$promotionId}_{$productId}_null";
            $promoItem = $this->promoItemsCache[$fallbackKey] ?? null;
        }

        if (! $promoItem || is_null($promoItem->promo_stock)) {
            return null;
        }

        $soldPromoQty = 0;
        if (is_null($promoItem->product_variant_id)) {
            $prefix = "{$promotionId}_{$productId}_";
            foreach ($this->soldPromoQuantitiesCache as $key => $usedQty) {
                if (str_starts_with($key, $prefix)) {
                    $soldPromoQty += $usedQty;
                }
            }
        } else {
            $soldPromoQty = $this->soldPromoQuantitiesCache[$cacheKey] ?? 0;
        }

        return max(0, $promoItem->promo_stock - $soldPromoQty);
    }

    /**
     * Helper to apply active promotions on a product and its variants.
     */
    private function applyPromotionsToProduct(Product $product, $activePromotions): void
    {
        $basePrice = $product->productPrice?->price ?? 0;

        // Resolve promotion candidates once
        // 1. Flash Sale
        $flashSalePromo = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'flash_sale') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true; // Store-wide
            }

            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
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

            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        // 4. Promo Toko / Special Deals
        $promoToko = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'promo_tok' && $promo->type !== 'special_deals' && $promo->type !== 'promo_toko') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }

            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        $appliedPromo = null;
        $appliedItem = null;

        // Clear out any previous promo rule
        $product->promo_rule = null;

        // 1. Try Flash Sale
        if ($flashSalePromo) {
            $remainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, null);
            if (! is_null($remainingStock)) {
                $product->promo_rule = [
                    'id' => $flashSalePromo->id,
                    'name' => $flashSalePromo->name,
                    'type' => $flashSalePromo->type,
                    'min_qty' => 1,
                    'remaining_promo_stock' => $remainingStock,
                ];
            }

            if (is_null($remainingStock) || $remainingStock > 0) {
                $appliedPromo = $flashSalePromo;
                if ($appliedPromo->items->isNotEmpty()) {
                    $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }
            }
        }

        // 2. If Flash Sale didn't apply, check if Bundling is active (skips general promo)
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

            $remainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, null);

            $product->promo_rule = [
                'id' => $promoProduk->id,
                'name' => $promoProduk->name,
                'type' => $promoProduk->type,
                'min_qty' => (int) $minQty,
                'discount_type' => $resolvedDiscountType,
                'discount_value' => $resolvedDiscountValue,
                'promo_price' => $resolvedPromoPrice ? (float) $resolvedPromoPrice : null,
                'remaining_promo_stock' => $remainingStock,
            ];

            if ((is_null($remainingStock) || $remainingStock > 0) && $minQty == 1) {
                $appliedPromo = $promoProduk;
                $appliedItem = $pItem;
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
                $finalPrice = (float) $promoPrice;
            } else {
                if ($discountType === 'percentage') {
                    $finalPrice = $basePrice - ($basePrice * ($discountValue / 100));
                } elseif ($discountType === 'fixed') {
                    $finalPrice = $basePrice - $discountValue;
                } else {
                    $finalPrice = $basePrice;
                }
            }

            $finalPrice = max(0, $finalPrice);

            if ($finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
                $product->promo_type = $appliedPromo->type;
                $product->promo_end_time = $appliedPromo->end_time?->toIso8601String();
                $product->keep_tier_prices = $appliedPromo->settings['keep_tier_prices'] ?? false;
                if ($basePrice > 0) {
                    $product->discount_percentage = round((($basePrice - $finalPrice) / $basePrice) * 100);
                } else {
                    $product->discount_percentage = 0;
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
                $vPrice = $variant->productPrice?->price ?? 0;
                $vAppliedPromo = null;
                $vAppliedItem = null;
                $variant->promo_rule = null;

                // 1. Try Flash Sale
                if ($flashSalePromo) {
                    $vRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, $variant->id);
                    if (! is_null($vRemainingStock)) {
                        $variant->promo_rule = [
                            'id' => $flashSalePromo->id,
                            'name' => $flashSalePromo->name,
                            'type' => $flashSalePromo->type,
                            'min_qty' => 1,
                            'remaining_promo_stock' => $vRemainingStock,
                        ];
                    }

                    if (is_null($vRemainingStock) || $vRemainingStock > 0) {
                        $vAppliedPromo = $flashSalePromo;
                        if ($vAppliedPromo->items->isNotEmpty()) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                            });
                            if (! $vAppliedItem) {
                                $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                                });
                            }
                        }
                    }
                }

                // 2. If Flash Sale didn't apply, check if Bundling is active
                if (! $vAppliedPromo && $isBundlingActive) {
                    $vAppliedPromo = null;
                }

                // 3. Try Promo Produk
                if (! $vAppliedPromo && ! $isBundlingActive && $promoProduk) {
                    $minQty = $promoProduk->settings['min_qty'] ?? 1;
                    $vAppliedItem = null;
                    if ($promoProduk->items->isNotEmpty()) {
                        $vAppliedItem = $promoProduk->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $vAppliedItem) {
                            $vAppliedItem = $promoProduk->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                    }

                    $vResolvedDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                    $vResolvedDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                    $vResolvedPromoPrice = $vAppliedItem?->promo_price;

                    $vRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, $variant->id);

                    $variant->promo_rule = [
                        'id' => $promoProduk->id,
                        'name' => $promoProduk->name,
                        'type' => $promoProduk->type,
                        'min_qty' => (int) $minQty,
                        'discount_type' => $vResolvedDiscountType,
                        'discount_value' => $vResolvedDiscountValue,
                        'promo_price' => $vResolvedPromoPrice ? (float) $vResolvedPromoPrice : null,
                        'remaining_promo_stock' => $vRemainingStock,
                    ];

                    if ((is_null($vRemainingStock) || $vRemainingStock > 0) && $minQty == 1) {
                        $vAppliedPromo = $promoProduk;
                    }
                }

                // 4. Try Promo Toko / Special Deals
                if (! $vAppliedPromo && ! $isBundlingActive && $promoToko) {
                    $vAppliedPromo = $promoToko;
                    if ($vAppliedPromo->items->isNotEmpty()) {
                        $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $vAppliedItem) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vFinalPrice = (float) $vPromoPrice;
                    } else {
                        if ($vDiscountType === 'percentage') {
                            $vFinalPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                        } elseif ($vDiscountType === 'fixed') {
                            $vFinalPrice = $vPrice - $vDiscountValue;
                        } else {
                            $vFinalPrice = $vPrice;
                        }
                    }

                    $vFinalPrice = max(0, $vFinalPrice);

                    if ($vFinalPrice < $vPrice) {
                        $variant->is_promo = true;
                        $variant->promo_price = $vFinalPrice;
                        $variant->original_price = $vPrice;
                        $variant->promo_type = $vAppliedPromo->type;
                        $variant->promo_end_time = $vAppliedPromo->end_time?->toIso8601String();
                        $variant->keep_tier_prices = $vAppliedPromo->settings['keep_tier_prices'] ?? false;
                        if ($vPrice > 0) {
                            $variant->discount_percentage = round((($vPrice - $vFinalPrice) / $vPrice) * 100);
                        } else {
                            $variant->discount_percentage = 0;
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

            // After applying to variants, we check if we should override product base price
            $promoVariants = $product->variants->filter(function ($v) {
                return $v->is_promo;
            });

            if ($promoVariants->isNotEmpty()) {
                // Find the cheapest promo variant by promo_price
                $cheapestPromoVariant = $promoVariants->sortBy('promo_price')->first();

                $product->is_promo = true;
                $product->promo_price = $cheapestPromoVariant->promo_price;
                $product->original_price = $cheapestPromoVariant->original_price;
                $product->discount_percentage = $cheapestPromoVariant->discount_percentage;
                $product->promo_type = $cheapestPromoVariant->promo_type;
                $product->promo_end_time = $cheapestPromoVariant->promo_end_time;
            } elseif ($product->is_promo) {
                // Keep base promo details
            } else {
                // Find the cheapest variant overall
                $cheapestVariant = $product->variants->sortBy(function ($v) {
                    return $v->productPrice?->price ?? 0;
                })->first();

                if ($cheapestVariant) {
                    $vPrice = $cheapestVariant->productPrice?->price ?? $basePrice;
                    $product->is_promo = false;
                    $product->promo_price = $vPrice;
                    $product->original_price = $vPrice;
                    $product->discount_percentage = 0;
                }
            }
        }

        // Apply membership discount fields (does not override promo)
        $this->applyMembershipToProduct($product);
    }
}
