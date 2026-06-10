<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Notification;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\KomerceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Display the storefront homepage.
     */
    public function index(Request $request)
    {
        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        $featuredProducts = Product::with([
            'category',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews as review_count')
            ->where('active', true)
            ->latest()
            ->take(12)
            ->get();

        $newProducts = Product::with([
            'category',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews as review_count')
            ->where('active', true)
            ->latest()
            ->take(50)
            ->get();

        // Fetch best-seller products sorted by actual sold quantity from completed transactions
        $bestSellerIds = DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'selesai')
            ->groupBy('transaction_items.product_id')
            ->selectRaw('transaction_items.product_id, SUM(transaction_items.quantity) as total_sold')
            ->orderByDesc('total_sold')
            ->take(10)
            ->pluck('product_id')
            ->all();

        // Load best-seller products with avg_rating and review count
        $bestSellerProducts = Product::with([
            'category',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews as review_count')
            ->where('active', true)
            ->whereIn('id', $bestSellerIds)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $bestSellerIds))
            ->values();

        // If there are fewer than 10 best sellers, pad with latest active products
        if ($bestSellerProducts->count() < 10) {
            $excludeIds = $bestSellerProducts->pluck('id')->all();
            $pad = Product::with([
                'category',
                'productPrice',
                'productStock',
                'images',
                'variants.productPrice',
                'variants.options',
                'variants.productStock',
                'variations.options',
            ])
                ->withAvg('reviews as avg_rating', 'rating')
                ->withCount('reviews as review_count')
                ->where('active', true)
                ->whereNotIn('id', $excludeIds)
                ->latest()
                ->take(10 - $bestSellerProducts->count())
                ->get();
            $bestSellerProducts = $bestSellerProducts->concat($pad);
        }

        $activeFlashSale = Promotion::with([
            'items.product.productPrice',
            'items.product.images',
            'items.variant.productPrice',
            'items.variant.options',
        ])
            ->where('type', 'flash_sale')
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->latest()
            ->first();

        if ($activeFlashSale) {
            foreach ($activeFlashSale->items as $item) {
                $remainingStock = $this->getRemainingPromoStock($activeFlashSale->id, $item->product_id, $item->product_variant_id);
                $item->setAttribute('remaining_promo_stock', $remainingStock);
            }
        }

        // Retrieve active store promotions (excluding flash sale as it is handled separately)
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        foreach ($featuredProducts as $p) {
            $this->applyPromotionsToProduct($p, $activePromotions);
        }

        foreach ($newProducts as $p) {
            $this->applyPromotionsToProduct($p, $activePromotions);
        }

        foreach ($bestSellerProducts as $p) {
            $this->applyPromotionsToProduct($p, $activePromotions);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        $heroBannersJson = Setting::where('key', 'hero_banners')->value('value');
        $heroBanners = $heroBannersJson ? json_decode($heroBannersJson, true) : [];

        $sideBannersJson = Setting::where('key', 'side_banners')->value('value');
        $sideBanners = $sideBannersJson ? json_decode($sideBannersJson, true) : [];

        $middleWideBannerJson = Setting::where('key', 'middle_wide_banner')->value('value');
        $middleWideBanner = $middleWideBannerJson ? json_decode($middleWideBannerJson, true) : null;

        $popupBannerJson = Setting::where('key', 'popup_banner')->value('value');
        $popupBanner = $popupBannerJson ? json_decode($popupBannerJson, true) : null;

        $recentReviews = ProductReview::with(['user', 'product.images', 'productVariant.options'])
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('Storefront/Home', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'newProducts' => $newProducts,
            'bestSellerProducts' => $bestSellerProducts,
            'activeFlashSale' => $activeFlashSale,
            'recentReviews' => $recentReviews,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'heroBanners' => $heroBanners,
            'sideBanners' => $sideBanners,
            'middleWideBanner' => $middleWideBanner,
            'popupBanner' => $popupBanner,
        ]);
    }

    /**
     * Display a single product detail page.
     */
    public function show(Product $product)
    {
        $product->load([
            'category',
            'categories',
            'brands',
            'productPrice',
            'productStock',
            'images',
            'variations.options',
            'variants.productPrice',
            'variants.productStock',
            'variants.options',
            'tierPrices',
            'variants.tierPrices',
        ]);

        // Calculate actual sold count from completed transactions
        $soldCount = TransactionItem::where('product_id', $product->id)
            ->whereHas('transaction', fn ($q) => $q->where('status', 'selesai'))
            ->sum('quantity');

        $product->sold_count = (int) $soldCount;

        $relatedProducts = Product::with([
            'productPrice',
            'images',
            'category',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->where('active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(8)
            ->get();

        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        $this->applyPromotionsToProduct($product, $activePromotions);

        foreach ($relatedProducts as $rp) {
            $this->applyPromotionsToProduct($rp, $activePromotions);
        }

        // Fetch active bundling promotions that apply to this product (i.e. this product is in buy_items)
        $bundlingPromos = $activePromotions->filter(function ($promo) use ($product) {
            if ($promo->type !== 'bundling_gift') {
                return false;
            }
            $bundle = $promo->settings['bundle'] ?? null;
            if (! $bundle || ! isset($bundle['buy_items'])) {
                return false;
            }
            foreach ($bundle['buy_items'] as $buyItem) {
                if (isset($buyItem['product_id']) && $buyItem['product_id'] == $product->id) {
                    return true;
                }
            }

            return false;
        });

        // Populate product info for the matched bundling promotions
        $bundlingPromos->each(function ($promo) {
            $bundle = $promo->settings['bundle'];

            // Load buy_items products
            if (isset($bundle['buy_items'])) {
                foreach ($bundle['buy_items'] as &$buyItem) {
                    if (! empty($buyItem['product_id'])) {
                        $prod = Product::with(['productPrice', 'images'])->find($buyItem['product_id']);
                        if ($prod) {
                            $buyItem['product_name'] = $prod->name;
                            $buyItem['product_slug'] = $prod->slug;
                            $buyItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                            $buyItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                        }
                    }
                }
            }

            // Load get_items products
            if (isset($bundle['get_items'])) {
                foreach ($bundle['get_items'] as &$getItem) {
                    if (! empty($getItem['product_id'])) {
                        $prod = Product::with(['productPrice', 'images'])->find($getItem['product_id']);
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
        });

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        $reviews = ProductReview::with(['user', 'productVariant.options'])
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        // Fetch shipping-related settings for display on product page
        $shippingSettings = Setting::whereIn('key', [
            'address',
            'district_name',
            'regency_name',
            'province_name',
            'postal_code',
            'shipping_rate',
            'enable_cod',
        ])->pluck('value', 'key');

        /** @var array{address: string, district_name: string, regency_name: string, province_name: string, postal_code: string, shipping_rate: string, enable_cod: string} $shippingSettings */
        $shippingInfo = [
            'store_address' => trim(($shippingSettings['address'] ?? '').', '.($shippingSettings['district_name'] ?? '')),
            'store_city' => trim(($shippingSettings['regency_name'] ?? '').', '.($shippingSettings['province_name'] ?? '')),
            'postal_code' => $shippingSettings['postal_code'] ?? '',
            'shipping_rate' => (int) ($shippingSettings['shipping_rate'] ?? 0),
            'enable_cod' => ($shippingSettings['enable_cod'] ?? '0') === '1',
        ];

        return Inertia::render('Storefront/Product', [
            'product' => $product,
            'reviews' => $reviews,
            'relatedProducts' => $relatedProducts,
            'storeName' => $storeName,
            'bundlingPromos' => $bundlingPromos->values(),
            'shippingInfo' => $shippingInfo,
        ]);
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
     * Helper to apply active promotions on a product and its variants.
     */
    private function applyPromotionsToProduct(Product $product, $activePromotions)
    {
        $basePrice = $product->productPrice?->price ?? 0;

        // Find if this product is in any active Flash Sale
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

        // Find if this product is in any active Promo Bundling
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

        $appliedPromo = null;
        $appliedItem = null;

        // Clear out any previous promo rule
        $product->promo_rule = null;

        if ($flashSalePromo) {
            // Priority 1: Flash Sale
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
        } elseif ($isBundlingActive) {
            // Priority 2: Promo Bundling is active, so we skip Promo Produk entirely!
            $appliedPromo = null;
        } else {
            // Priority 3: Promo Produk (and other general store promos like promo_toko if any)
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

            if ($promoProduk) {
                $minQty = $promoProduk->settings['min_qty'] ?? 1;

                $appliedItem = null;
                if ($promoProduk->items->isNotEmpty()) {
                    $appliedItem = $promoProduk->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }

                $resolvedDiscountType = $appliedItem ? ($appliedItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                $resolvedDiscountValue = $appliedItem ? ($appliedItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                $resolvedPromoPrice = $appliedItem?->promo_price;

                $remainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, null);

                // Expose the promo rule regardless of min_qty so frontend can see it
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
                } else {
                    $appliedPromo = null;
                }
            } else {
                // Fallback to promo_toko
                $promoToko = $activePromotions->first(function ($promo) use ($product) {
                    if ($promo->type !== 'promo_toko' && $promo->type !== 'special_deals') {
                        return false;
                    }
                    if ($promo->items->isEmpty()) {
                        return true;
                    }

                    return $promo->items->contains(function ($i) use ($product) {
                        return $i->product_id === $product->id;
                    });
                });

                if ($promoToko) {
                    $appliedPromo = $promoToko;
                    if ($appliedPromo->items->isNotEmpty()) {
                        $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                            return $i->product_id === $product->id && is_null($i->product_variant_id);
                        });
                    }
                }
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
                } elseif ($isBundlingActive) {
                    $vAppliedPromo = null;
                } else {
                    if (isset($promoProduk)) {
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
                    } else {
                        if (isset($promoToko)) {
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

            // Aggregate: find the cheapest variant to display on the base product card
            if ($product->variants->isNotEmpty()) {
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
                    // Keep base promo details (it's already calculated and variants are not on promo)
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
        }
    }

    /**
     * Display the storefront search/catalog listing page.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $brandId = $request->input('brand');
        $sort = $request->input('sort', 'relevance');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $promoOnly = $request->boolean('promo');

        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        $brands = Brand::where('is_active', true)
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        // Build product query
        $productsQuery = Product::with([
            'category',
            'categories',
            'brandRelation',
            'brands',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->where('active', true);

        $like = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';

        // Filter by keyword (search in name, description, category name, variant SKU, and variant options)
        if ($query) {
            $terms = array_filter(explode(' ', $query)); // Pisahkan kata berdasarkan spasi
            $productsQuery->where(function ($q) use ($terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($subQ) use ($term, $like) {
                        // Cari di nama dan deskripsi produk
                        $subQ->where('name', $like, "%{$term}%")
                            ->orWhere('description', $like, "%{$term}%")
                            // Cari di nama kategori
                            ->orWhereHas('category', function ($qc) use ($term, $like) {
                                $qc->where('name', $like, "%{$term}%");
                            })
                            ->orWhereHas('categories', function ($qc) use ($term, $like) {
                                $qc->where('name', $like, "%{$term}%");
                            })
                            // Cari di SKU varian atau nama opsi varian (contoh: "Merah", "XL")
                            ->orWhereHas('variants', function ($qv) use ($term, $like) {
                                $qv->where('sku', $like, "%{$term}%")
                                    ->orWhereHas('options', function ($qo) use ($term, $like) {
                                        $qo->where('name', $like, "%{$term}%");
                                    });
                            });
                    });
                }
            });
        }

        // Filter by category
        if ($categoryId) {
            $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
            $uuids = [];
            $slugs = [];

            foreach ($categoryIds as $cat) {
                if (is_string($cat)) {
                    $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cat);
                    if ($isUuid) {
                        $uuids[] = $cat;
                    } else {
                        $slugs[] = $cat;
                    }
                }
            }

            $productsQuery->where(function ($q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('category_id', $uuids)
                        ->orWhereHas('categories', function ($sub) use ($uuids) {
                            $sub->whereIn('categories.id', $uuids);
                        });
                }
                if (! empty($slugs)) {
                    $q->orWhereHas('category', function ($sub) use ($slugs) {
                        $sub->whereIn('slug', $slugs);
                    })
                        ->orWhereHas('categories', function ($sub) use ($slugs) {
                            $sub->whereIn('categories.slug', $slugs);
                        });
                }
            });
        }

        // Filter by brand
        if ($brandId) {
            $brandIds = is_array($brandId) ? $brandId : [$brandId];
            $uuids = [];
            $slugs = [];

            foreach ($brandIds as $br) {
                if (is_string($br)) {
                    $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $br);
                    if ($isUuid) {
                        $uuids[] = $br;
                    } else {
                        $slugs[] = $br;
                    }
                }
            }

            $productsQuery->where(function ($q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('brand_id', $uuids)
                        ->orWhereHas('brands', function ($sub) use ($uuids) {
                            $sub->whereIn('brands.id', $uuids);
                        });
                }
                if (! empty($slugs)) {
                    $q->orWhereIn('brand', $slugs)
                        ->orWhereHas('brandRelation', function ($sub) use ($slugs) {
                            $sub->whereIn('slug', $slugs);
                        })
                        ->orWhereHas('brands', function ($sub) use ($slugs) {
                            $sub->whereIn('brands.slug', $slugs);
                        });
                }
            });
        }

        // Retrieve active promotions to map onto products dynamically
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        // Retrieve all filtered products first
        $productsCollection = $productsQuery->get();

        // Apply promotions and filter collection in a single pass for better performance
        $productsCollection = $productsCollection->filter(function ($p) use ($activePromotions, $promoOnly, $minPrice, $maxPrice) {
            // 1. Apply promotion data
            $this->applyPromotionsToProduct($p, $activePromotions);

            // 2. Filter by 'promo only'
            if ($promoOnly && ! $p->is_promo) {
                return false;
            }

            // 3. Filter by price range
            $price = $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            if ($minPrice && $price < $minPrice) {
                return false;
            }
            if ($maxPrice && $price > $maxPrice) {
                return false;
            }

            return true;
        });

        // Apply sorting on the mapped collection
        if ($sort === 'price_asc') {
            $productsCollection = $productsCollection->sortBy(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'price_desc') {
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'popular') {
            $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
            $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                return $soldCounts[$p->id] ?? 0;
            });
        } elseif ($sort === 'latest') {
            $productsCollection = $productsCollection->sortByDesc('created_at');
        } else {
            // Default: relevance
            if ($query) {
                $productsCollection = $productsCollection->sortByDesc(function ($p) use ($query) {
                    $nameLower = strtolower($p->name);
                    $queryLower = strtolower($query);
                    if (str_starts_with($nameLower, $queryLower)) {
                        return 2;
                    }
                    if (str_contains($nameLower, $queryLower)) {
                        return 1;
                    }

                    return 0;
                });
            } else {
                $productsCollection = $productsCollection->sortByDesc('created_at');
            }
        }

        // Manually paginate the collection
        $page = request()->input('page', 1);
        $perPage = 16;
        $total = $productsCollection->count();

        $paginatedItems = $productsCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $productsPaginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Search', [
            'categories' => $categories,
            'brands' => $brands,
            'products' => $productsPaginator,
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
                'brand' => $brandId,
                'sort' => $sort,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'promo' => $promoOnly,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the flash sale listing page.
     */
    public function flashSale(Request $request)
    {
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'relevance');

        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        // 1. Fetch active flash sale
        $activeFlashSale = Promotion::with([
            'items.product.productPrice',
            'items.product.images',
            'items.product.category',
            'items.variant.productPrice',
            'items.variant.options',
        ])
            ->where('type', 'flash_sale')
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->latest()
            ->first();

        $productsCollection = collect();

        if ($activeFlashSale) {
            // Build products collection from flash sale items
            if ($activeFlashSale->items->isNotEmpty()) {
                foreach ($activeFlashSale->items as $item) {
                    if ($item->product) {
                        $p = clone $item->product;

                        $remainingStock = $this->getRemainingPromoStock($activeFlashSale->id, $item->product_id, $item->product_variant_id);
                        $p->setAttribute('remaining_promo_stock', $remainingStock);
                        $p->setAttribute('promo_stock', $item->promo_stock);
                        $p->setAttribute('product_variant_id', $item->product_variant_id);

                        if ($item->variant) {
                            if ($item->variant->productPrice) {
                                $p->setRelation('productPrice', $item->variant->productPrice);
                            }
                            $optionNames = $item->variant->options
                                ? $item->variant->options->map(fn ($o) => $o->name)->join(' - ')
                                : '';
                            if ($optionNames) {
                                $p->name = "{$p->name} - {$optionNames}";
                            }
                            if ($item->variant->image) {
                                $p->image = $item->variant->image;
                                $p->setRelation('images', collect());
                            }
                        }
                        $productsCollection->push($p);
                    }
                }
            } else {
                // If flash sale has no items, it applies to all products
                $productsCollection = Product::with([
                    'category',
                    'productPrice',
                    'productStock',
                    'images',
                    'variants.productPrice',
                    'variants.options',
                    'variants.productStock',
                    'variations.options',
                ])
                    ->where('active', true)
                    ->get();
            }
        }

        // Filter and map collection
        $productsCollection = $productsCollection->filter(function ($p) use ($activeFlashSale, $query, $categoryId, $minPrice, $maxPrice) {
            if ($activeFlashSale) {
                // Force flash sale promotion
                $basePrice = $p->productPrice?->price ?? 0;
                $discountType = $activeFlashSale->discount_type;
                $discountValue = $activeFlashSale->discount_value;

                // Check if there is an item specific discount override
                if ($activeFlashSale->items->isNotEmpty()) {
                    $item = $activeFlashSale->items->firstWhere('product_id', $p->id);
                    if ($item) {
                        $discountType = $item->discount_type ?? $discountType;
                        $discountValue = $item->discount_value ?? $discountValue;
                    }
                }

                // Check if there is an item specific discount override
                if ($activeFlashSale->items->isNotEmpty()) {
                    $item = $activeFlashSale->items->first(function ($i) use ($p) {
                        return $i->product_id === $p->id && ($p->product_variant_id ? $i->product_variant_id === $p->product_variant_id : is_null($i->product_variant_id));
                    });
                    if ($item) {
                        $discountType = $item->discount_type ?? $discountType;
                        $discountValue = $item->discount_value ?? $discountValue;
                    }
                }

                $remainingStock = $p->remaining_promo_stock;

                if (is_null($remainingStock) || $remainingStock > 0) {
                    if ($discountType === 'percentage') {
                        $finalPrice = $basePrice - ($basePrice * ($discountValue / 100));
                    } elseif ($discountType === 'fixed') {
                        $finalPrice = $basePrice - $discountValue;
                    } else {
                        $finalPrice = $basePrice;
                    }

                    $finalPrice = max(0, $finalPrice);
                    $p->is_promo = true;
                    $p->promo_price = $finalPrice;
                    $p->original_price = $basePrice;
                    if ($basePrice > 0) {
                        $p->discount_percentage = round((($basePrice - $finalPrice) / $basePrice) * 100);
                    } else {
                        $p->discount_percentage = 0;
                    }
                } else {
                    // STOCK IS EXHAUSTED -> REVERT TO NORMAL PRICE!
                    $p->is_promo = false;
                    $p->promo_price = $basePrice;
                    $p->original_price = $basePrice;
                    $p->discount_percentage = 0;
                }
            }

            // Keyword filter
            if ($query) {
                $terms = array_filter(explode(' ', strtolower($query)));
                $nameLower = strtolower($p->name);
                $descLower = strtolower($p->description ?? '');
                $categoryNameLower = strtolower($p->category?->name ?? '');
                foreach ($terms as $term) {
                    if (
                        ! str_contains($nameLower, $term) &&
                        ! str_contains($descLower, $term) &&
                        ! str_contains($categoryNameLower, $term)
                    ) {
                        return false;
                    }
                }
            }

            // Category filter
            if ($categoryId) {
                $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
                $match = false;
                foreach ($categoryIds as $cat) {
                    if ($p->category_id === $cat || $p->category?->slug === $cat || $p->category?->id === $cat) {
                        $match = true;
                        break;
                    }
                }
                if (! $match) {
                    return false;
                }
            }

            // Price range filter
            $price = $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            if ($minPrice && $price < $minPrice) {
                return false;
            }
            if ($maxPrice && $price > $maxPrice) {
                return false;
            }

            return true;
        });

        // apply dynamic sorting
        if ($sort === 'price_asc') {
            $productsCollection = $productsCollection->sortBy(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'price_desc') {
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'latest') {
            $productsCollection = $productsCollection->sortByDesc('created_at');
        } elseif ($sort === 'popular') {
            $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
            $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                return $soldCounts[$p->id] ?? 0;
            });
        } else {
            // relevance / discount_desc
            $productsCollection = $productsCollection->sortByDesc('discount_percentage');
        }

        // Paginate
        $page = request()->input('page', 1);
        $perPage = 16;
        $total = $productsCollection->count();
        $paginatedItems = $productsCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $productsPaginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/FlashSale', [
            'categories' => $categories,
            'products' => $productsPaginator,
            'activeFlashSale' => $activeFlashSale,
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the best sellers listing page.
     */
    public function produkTerlaris(Request $request)
    {
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'popular');

        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        // Fetch active promotions to map onto products dynamically
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        // Build product query
        $productsQuery = Product::with([
            'category',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->where('active', true);

        $like = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';

        // Filter by keyword (similar to search method)
        if ($query) {
            $terms = array_filter(explode(' ', $query));
            $productsQuery->where(function ($q) use ($terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($subQ) use ($term, $like) {
                        $subQ->where('name', $like, "%{$term}%")
                            ->orWhere('description', $like, "%{$term}%")
                            ->orWhereHas('category', function ($qc) use ($term, $like) {
                                $qc->where('name', $like, "%{$term}%");
                            })
                            ->orWhereHas('variants', function ($qv) use ($term, $like) {
                                $qv->where('sku', $like, "%{$term}%")
                                    ->orWhereHas('options', function ($qo) use ($term, $like) {
                                        $qo->where('name', $like, "%{$term}%");
                                    });
                            });
                    });
                }
            });
        }

        // Filter by category
        if ($categoryId) {
            $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
            $uuids = [];
            $slugs = [];

            foreach ($categoryIds as $cat) {
                if (is_string($cat)) {
                    $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cat);
                    if ($isUuid) {
                        $uuids[] = $cat;
                    } else {
                        $slugs[] = $cat;
                    }
                }
            }

            $productsQuery->where(function ($q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('category_id', $uuids);
                }
                if (! empty($slugs)) {
                    $q->orWhereHas('category', function ($sub) use ($slugs) {
                        $sub->whereIn('slug', $slugs);
                    });
                }
            });
        }

        $productsCollection = $productsQuery->get();

        // Apply promotions and filter by price range
        $productsCollection = $productsCollection->filter(function ($p) use ($activePromotions, $minPrice, $maxPrice) {
            $this->applyPromotionsToProduct($p, $activePromotions);

            $price = $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            if ($minPrice && $price < $minPrice) {
                return false;
            }
            if ($maxPrice && $price > $maxPrice) {
                return false;
            }

            return true;
        });

        // Apply sorting on the mapped collection
        if ($sort === 'price_asc') {
            $productsCollection = $productsCollection->sortBy(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'price_desc') {
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'latest') {
            $productsCollection = $productsCollection->sortByDesc('created_at');
        } elseif ($sort === 'popular') {
            $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
            $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                return $soldCounts[$p->id] ?? 0;
            });
        } else {
            // Default sort: by actual sold count descending (best sellers first)
            $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
            if ($query) {
                $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                    return $soldCounts[$p->id] ?? 0;
                });
            } else {
                $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                    return $soldCounts[$p->id] ?? 0;
                });
            }
        }

        // Paginate
        $page = request()->input('page', 1);
        $perPage = 16;
        $total = $productsCollection->count();

        // Attach sold_count to each product for display
        $allProductIds = $productsCollection->pluck('id')->all();
        $soldCountsMap = $this->getSoldCountsForProducts($allProductIds);
        foreach ($productsCollection as $p) {
            $p->sold_count = $soldCountsMap[$p->id] ?? 0;
        }

        $paginatedItems = $productsCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $productsPaginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/ProdukTerlaris', [
            'categories' => $categories,
            'products' => $productsPaginator,
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the products in a specific category.
     *
     * @return Response
     */
    public function category(Request $request, string $category)
    {
        $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $category);

        $categoryModel = $isUuid
            ? Category::where('id', $category)->firstOrFail()
            : Category::where('slug', $category)->firstOrFail();

        $query = $request->input('q');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'latest');
        $type = $request->input('type', 'all');

        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        // Build product query
        $productsQuery = Product::with([
            'category',
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->where('active', true)
            ->where('category_id', $categoryModel->id);

        if ($type === 'physical') {
            $productsQuery->where('is_digital', false);
        } elseif ($type === 'digital') {
            $productsQuery->where('is_digital', true);
        }

        $like = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';

        // Filter by keyword (search in name, description, variant SKU, etc.)
        if ($query) {
            $terms = array_filter(explode(' ', $query));
            $productsQuery->where(function ($q) use ($terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($subQ) use ($term, $like) {
                        $subQ->where('name', $like, "%{$term}%")
                            ->orWhere('description', $like, "%{$term}%")
                            ->orWhereHas('variants', function ($qv) use ($term, $like) {
                                $qv->where('sku', $like, "%{$term}%")
                                    ->orWhereHas('options', function ($qo) use ($term, $like) {
                                        $qo->where('name', $like, "%{$term}%");
                                    });
                            });
                    });
                }
            });
        }

        // Fetch promotions to apply dynamically
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        $productsCollection = $productsQuery->get();

        // Apply promotions and price filters
        $productsCollection = $productsCollection->filter(function ($p) use ($activePromotions, $minPrice, $maxPrice) {
            $this->applyPromotionsToProduct($p, $activePromotions);

            $price = $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            if ($minPrice && $price < $minPrice) {
                return false;
            }
            if ($maxPrice && $price > $maxPrice) {
                return false;
            }

            return true;
        });

        // Apply sorting on the mapped collection
        if ($sort === 'price_asc') {
            $productsCollection = $productsCollection->sortBy(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'price_desc') {
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
            });
        } elseif ($sort === 'popular') {
            $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
            $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                return $soldCounts[$p->id] ?? 0;
            });
        } elseif ($sort === 'latest') {
            $productsCollection = $productsCollection->sortByDesc('created_at');
        } else {
            if ($query) {
                $productsCollection = $productsCollection->sortByDesc(function ($p) use ($query) {
                    $nameLower = strtolower($p->name);
                    $queryLower = strtolower($query);
                    if (str_starts_with($nameLower, $queryLower)) {
                        return 2;
                    }
                    if (str_contains($nameLower, $queryLower)) {
                        return 1;
                    }

                    return 0;
                });
            } else {
                $productsCollection = $productsCollection->sortByDesc('created_at');
            }
        }

        // Paginate
        $page = request()->input('page', 1);
        $perPage = 16;
        $total = $productsCollection->count();
        $paginatedItems = $productsCollection->slice(($page - 1) * $perPage, $perPage)->values();

        $productsPaginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Category', [
            'category' => $categoryModel,
            'categories' => $categories,
            'products' => $productsPaginator,
            'filters' => [
                'q' => $query,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
                'type' => $type,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Handle customer registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole('Customer');

        event(new Registered($user));

        return redirect('/login')->with('success', 'Pendaftaran berhasil! Silakan periksa email Anda untuk memverifikasi akun sebelum masuk.');
    }

    /**
     * Display customer transaction history.
     */
    public function transactionHistory(Request $request)
    {
        Transaction::processAutoStatusUpdates($request->user()->id);

        $status = $request->input('status', 'all');

        $query = Transaction::with([
            'paymentMethod:id,name,type',
            'payment',
            'items',
        ])
            ->where('user_id', $request->user()->id);

        if ($status !== 'all') {
            if ($status === 'belum_bayar') {
                $query->where('status', 'belum_bayar');
            } elseif ($status === 'berjalan') {
                $query->whereIn('status', ['menunggu', 'diproses', 'dikemas', 'dikirim']);
            } elseif ($status === 'selesai') {
                $query->where('status', 'selesai');
            } elseif ($status === 'batal') {
                $query->where('status', 'batal');
            }
        }

        $transactions = $query->with('items.product')->latest()
            ->paginate(10)
            ->withQueryString();

        // Count for all statuses to display in the header tabs accurately
        $statusCounts = [
            'all' => Transaction::where('user_id', $request->user()->id)->count(),
            'belum_bayar' => Transaction::where('user_id', $request->user()->id)->where('status', 'belum_bayar')->count(),
            'berjalan' => Transaction::where('user_id', $request->user()->id)->whereIn('status', ['menunggu', 'diproses', 'dikemas', 'dikirim'])->count(),
            'selesai' => Transaction::where('user_id', $request->user()->id)->where('status', 'selesai')->count(),
            'batal' => Transaction::where('user_id', $request->user()->id)->where('status', 'batal')->count(),
        ];

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/TransactionHistory', [
            'transactions' => $transactions,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'currentStatus' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Display a single transaction detail for the customer.
     */
    public function transactionDetail(Request $request, Transaction $transaction)
    {
        Transaction::processAutoStatusUpdates($request->user()->id);

        // Sync Komerce payment methods to ensure they reflect current setting status and admin fees
        KomerceService::syncPaymentMethods();

        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items.product',
            'payments',
            'payment',
            'courier',
            'courierUser',
            'statusHistories',
            'returns.items',
            'returns.media',
            'activeReturn.items',
            'activeReturn.media',
            'activeRefundRequest',
            'refundRequests',
        ]);

        if ($transaction->status === 'belum_bayar' && $request->query('simulated_payment') == '1') {
            $latestPayment = $transaction->payment;
            if ($latestPayment) {
                DB::transaction(function () use ($transaction, $latestPayment) {
                    $latestPayment->update([
                        'status' => 'confirmed',
                        'gateway_status' => 'PAID',
                        'confirmed_at' => now(),
                    ]);

                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Simulated Payment Verified on Page Load', [
                        'transaction_id' => $transaction->id,
                    ]);
                });

                $transaction->load(['payments', 'payment']);
            }
        }

        $userReviews = ProductReview::where('user_id', $request->user()->id)
            ->where('transaction_id', $transaction->id)
            ->where('product_id', $validated['product_id'] ?? null) // keep context safe
            ->get(); // we'll fetch proper reviews below anyway

        // Let's resolve the user reviews properly
        $userReviews = ProductReview::where('user_id', $request->user()->id)
            ->where('transaction_id', $transaction->id)
            ->get()
            ->keyBy(function ($review) {
                return $review->product_id.'_'.$review->product_variant_id;
            });

        // Auto-check gateway payment status if the transaction is still unpaid and is a gateway payment
        if ($transaction->status === 'belum_bayar' && $transaction->paymentMethod?->type === 'gateway') {
            $latestPayment = $transaction->payment;

            if ($latestPayment) {
                $pmNameLower = strtolower($transaction->paymentMethod->name);

                if (str_contains($pmNameLower, 'qris (komerce)') || str_contains($pmNameLower, 'komerce payment')) {
                    try {
                        $refId = $latestPayment->gateway_transaction_id ?: $transaction->transaction_number;
                        $statusCheck = KomerceService::checkPaymentStatus($refId);

                        if ($statusCheck['success'] && ($statusCheck['status'] ?? '') === 'paid') {
                            DB::transaction(function () use ($transaction, $latestPayment) {
                                // Update Transaction Payment
                                $latestPayment->update([
                                    'status' => 'confirmed',
                                    'gateway_status' => 'PAID',
                                    'confirmed_at' => now(),
                                ]);

                                // Update Transaction Status
                                $transaction->update([
                                    'status' => 'diproses',
                                ]);

                                Log::info('Komerce Payment Auto-verified on Page Load', [
                                    'transaction_id' => $transaction->id,
                                    'status' => 'paid',
                                ]);
                            });

                            // Reload relations after update
                            $transaction->load(['payments', 'payment']);
                        }
                    } catch (\Exception $e) {
                        Log::error('Komerce Payment Auto-check Exception: '.$e->getMessage());
                    }
                } elseif (str_contains($pmNameLower, 'midtrans')) {
                    try {
                        $serverKey = $transaction->paymentMethod->api_key ?: config('app.midtrans.server_key');
                        $baseUrl = $transaction->paymentMethod->settings['url'] ?? config('app.midtrans.snap_url', 'https://app.sandbox.midtrans.com');

                        $isSandbox = str_contains($baseUrl, 'sandbox');
                        $apiUrl = $isSandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
                        $midtransUrl = rtrim($apiUrl, '/').'/v2/'.$transaction->transaction_number.'/status';

                        $response = Http::withBasicAuth($serverKey, '')
                            ->timeout(10)
                            ->get($midtransUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = $responseData['transaction_status'] ?? '';

                            if ($status === 'settlement' || $status === 'capture') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    // Update Transaction Payment
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    // Update Transaction Status
                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Midtrans Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'status' => $status,
                                    ]);
                                });

                                // Reload relations after update
                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Midtrans Auto-check Exception: '.$e->getMessage());
                    }
                } elseif (str_contains(strtolower($transaction->paymentMethod->name), 'flip')) {
                    try {
                        $secretKey = $transaction->paymentMethod->api_key ?: config('app.flip.secret_key');
                        $baseUrl = $transaction->paymentMethod->settings['url'] ?? config('app.flip.base_url', 'https://bigflip.id/big_sandbox_api');
                        $billId = $latestPayment->gateway_transaction_id;

                        $flipUrl = rtrim($baseUrl, '/').'/v2/pwf/'.$billId.'/bill';

                        $response = Http::withBasicAuth($secretKey, '')
                            ->timeout(10)
                            ->get($flipUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = $responseData['status'] ?? '';

                            if ($status === 'SUCCESSFUL' || $status === 'INACTIVE') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    // Update Transaction Payment
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    // Update Transaction Status
                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Flip Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'status' => $status,
                                    ]);
                                });

                                // Reload relations after update
                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Flip Auto-check Exception: '.$e->getMessage());
                    }
                } else {
                    try {
                        $invoiceId = $latestPayment->gateway_transaction_id;
                        $secretKey = $transaction->paymentMethod->api_secret ?: config('app.xendit.private_key');

                        $baseUrl = ($transaction->paymentMethod->settings && isset($transaction->paymentMethod->settings['url']))
                            ? $transaction->paymentMethod->settings['url']
                            : config('app.xendit.url', 'https://api.xendit.co');

                        $xenditUrl = rtrim($baseUrl, '/').'/v2/invoices/'.$invoiceId;

                        $response = Http::withBasicAuth($secretKey, '')
                            ->timeout(10)
                            ->get($xenditUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = strtoupper($responseData['status'] ?? '');

                            if ($status === 'PAID' || $status === 'SETTLED') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    // Update Transaction Payment
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    // Update Transaction Status
                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Xendit Invoice Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'invoice_id' => $latestPayment->gateway_transaction_id,
                                        'status' => $status,
                                    ]);
                                });

                                // Reload relations after update
                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Xendit Auto-check Exception: '.$e->getMessage());
                    }
                }
            }
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/TransactionDetail', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'returnStatusLabels' => Transaction::returnStatusLabels(),
            'paymentMethods' => PaymentMethod::select('id', 'name', 'type')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'userReviews' => $userReviews,
            'userBankAccounts' => $request->user()->customerBankAccounts()->orderByDesc('is_primary')->get(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Print customer transaction invoice.
     */
    public function printInvoice(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load([
            'user:id,name,email',
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return view('print.invoice', compact('transaction', 'storeName'));
    }

    /**
     * Cancel a transaction (customer-initiated).
     * Only allowed when status is belum_bayar or menunggu.
     */
    public function cancelTransaction(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            return redirect()->back()->with('error', 'Pesanan ini tidak dapat dibatalkan.');
        }

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $transaction->update([
            'status' => 'batal',
            'cancel_reason' => $validated['cancel_reason'],
            'cancelled_at' => now(),
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }

    /**
     * Change payment method for an unpaid transaction.
     * Only allowed when status is belum_bayar.
     */
    public function changePaymentMethod(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'belum_bayar') {
            return redirect()->back()->with('error', 'Metode pembayaran hanya bisa diubah untuk pesanan yang belum dibayar.');
        }

        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $paymentMethod = PaymentMethod::findOrFail($validated['payment_method_id']);

        $transaction->update([
            'payment_method_id' => $paymentMethod->id,
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Metode pembayaran berhasil diubah.');
    }

    /**
     * Complete a transaction (customer received the order).
     * Only allowed when status is dikirim.
     */
    public function completeTransaction(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'dikirim') {
            return redirect()->back()->with('error', 'Status pesanan harus dikirim terlebih dahulu.');
        }

        $transaction->update([
            'status' => 'selesai',
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan telah diterima. Terima kasih telah berbelanja!');
    }

    /**
     * Extend the order auto-complete confirmation period.
     * Only allowed when status is dikirim and is_extended is false.
     */
    public function extendAutoComplete(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'dikirim') {
            return redirect()->back()->with('error', 'Status pesanan harus dikirim terlebih dahulu.');
        }

        if ($transaction->is_extended) {
            return redirect()->back()->with('error', 'Jangka waktu konfirmasi pesanan ini sudah pernah diperpanjang sebelumnya.');
        }

        $days = (int) (Setting::where('key', 'extend_auto_complete_days')->value('value') ?? 3);

        $currentAutoComplete = $transaction->auto_complete_at ?: now();
        $transaction->update([
            'auto_complete_at' => $currentAutoComplete->addDays($days),
            'is_extended' => true,
        ]);

        // Add history log
        $transaction->statusHistories()->create([
            'status' => 'dikirim',
            'description' => "Jangka waktu konfirmasi pesanan diperpanjang selama {$days} hari.",
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', "Jangka waktu konfirmasi pesanan berhasil diperpanjang selama {$days} hari.");
    }

    /**
     * Submit a review for a specific product in a transaction.
     * Only allowed when status is selesai.
     */
    public function submitReview(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'selesai') {
            return redirect()->back()->with('error', 'Anda hanya bisa memberikan ulasan untuk pesanan yang telah selesai.');
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
            'files' => 'nullable|array',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webp|max:20480',
        ]);

        // Check if already reviewed
        $exists = ProductReview::where('user_id', $request->user()->id)
            ->where('transaction_id', $transaction->id)
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Anda sudah memberikan ulasan untuk produk ini.');
        }

        $mediaPaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('reviews', 'public');
                $mediaPaths[] = '/storage/'.$path;
            }
        }

        ProductReview::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'] ?? null,
            'transaction_id' => $transaction->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'media' => ! empty($mediaPaths) ? $mediaPaths : null,
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Terima kasih atas ulasan Anda!');
    }

    /**
     * Report a product review.
     */
    public function reportReview(Request $request, ProductReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($review->is_reported) {
            return redirect()->back()->with('error', 'Ulasan ini sudah pernah dilaporkan.');
        }

        $review->update([
            'is_reported' => true,
            'report_reason' => $validated['reason'],
            'reported_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Ulasan berhasil dilaporkan. Tim kami akan meninjaunya.');
    }

    /**
     * Get remaining promo stock for a promotion and product/variant.
     */
    private function getRemainingPromoStock($promotionId, $productId, $variantId = null): ?int
    {
        $promoItem = PromotionItem::where('promotion_id', $promotionId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if (! $promoItem && $variantId) {
            $promoItem = PromotionItem::where('promotion_id', $promotionId)
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->first();
        }

        if (! $promoItem || is_null($promoItem->promo_stock)) {
            return null;
        }

        $soldPromoQty = TransactionItem::where('applied_promotion_id', $promotionId)
            ->where('product_id', $productId)
            ->where(function ($q) use ($promoItem) {
                if (! is_null($promoItem->product_variant_id)) {
                    $q->where('product_variant_id', $promoItem->product_variant_id);
                }
            })
            ->whereHas('transaction', function ($q) {
                $q->where('status', '!=', 'batal');
            })
            ->sum('promo_quantity_used');

        return max(0, $promoItem->promo_stock - $soldPromoQty);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markNotificationAsRead(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->user_id === null) {
            if (! $request->user()->hasAnyRole(['Super Admin', 'Admin Penjualan', 'Admin Toko'])) {
                abort(403);
            }
        } elseif ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return redirect()->back();
    }

    /**
     * Mark all notifications of the user as read.
     */
    public function markAllNotificationsAsRead(Request $request): RedirectResponse
    {
        $type = $request->input('type');

        if ($type === 'admin' && $request->user()->hasAnyRole(['Super Admin', 'Admin Penjualan', 'Admin Toko'])) {
            Notification::whereNull('user_id')
                ->where('is_read', false)
                ->update(['is_read' => true]);
        } else {
            Notification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return redirect()->back();
    }
}
