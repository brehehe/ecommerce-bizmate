<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MembershipLevel;
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
use App\Services\MembershipService;
use App\Services\MidtransService;
use App\Services\ProductService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class StorefrontController extends Controller
{
    private ?array $promoItemsCache = null;

    private ?array $soldPromoQuantitiesCache = null;

    private float $memberDiscountPct = 0;

    public function __construct(private readonly MembershipService $membershipService)
    {
        // memberDiscountPct is initialized lazily via initMembership()
        // to avoid accessing auth() before the request lifecycle is ready
    }

    /**
     * Initialize membership discount for the current user.
     * Call once at the start of any storefront method that renders products.
     */
    private function initMembership(): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        $this->memberDiscountPct = $this->membershipService->getMembershipDiscountForUser($user);
    }

    /**
     * Search suggestions endpoint — returns brands, categories, and products matching a query.
     *
     * Supports multi-keyword search: "Raket Adidas Baru" will find products where
     * each word matches in any of: name, brand column, brand relation, category name,
     * summary, or description.
     */
    public function suggest(Request $request): JsonResponse
    {
        $raw = trim((string) $request->input('q', ''));

        if (mb_strlen($raw) < 2) {
            return response()->json(['brands' => [], 'categories' => [], 'products' => []]);
        }

        // Split query into individual keywords (max 5 keywords to avoid abuse)
        $keywords = array_slice(
            array_filter(preg_split('/\s+/', $raw)),
            0,
            5
        );

        // --- Brands ---
        $brands = Brand::select('id', 'name', 'slug')
            ->where('is_active', true)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->where('name', 'ilike', '%'.$kw.'%');
                }
            })
            ->orderBy('name')
            ->limit(4)
            ->get();

        // --- Categories ---
        $categories = Category::select('id', 'name', 'slug', 'icon', 'image')
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->where('name', 'ilike', '%'.$kw.'%');
                }
            })
            ->orderBy('order')
            ->limit(4)
            ->get();

        // --- Products: every keyword must match in at least one column/relation ---
        $products = Product::select('id', 'name', 'slug', 'brand', 'category_id', 'brand_id')
            ->with([
                'productPrice:id,product_id,price',
                'images:id,product_id,path,is_main,sort_order',
                'brandRelation:id,name',
                'category:id,name',
            ])
            ->activeAndNotExpired()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $kw) {
                    $query->where(function ($q) use ($kw) {
                        $q->where('name', 'ilike', '%'.$kw.'%')
                            ->orWhere('brand', 'ilike', '%'.$kw.'%')
                            ->orWhere('summary', 'ilike', '%'.$kw.'%')
                            ->orWhere('description', 'ilike', '%'.$kw.'%')
                            ->orWhereHas('brandRelation', fn ($b) => $b->where('name', 'ilike', '%'.$kw.'%'))
                            ->orWhereHas('category', fn ($c) => $c->where('name', 'ilike', '%'.$kw.'%'));
                    });
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Product $product): array {
                $image = $product->images->first();
                $imagePath = $image?->path;

                if ($imagePath && ! str_starts_with($imagePath, 'http') && ! str_starts_with($imagePath, '/')) {
                    $imagePath = '/'.$imagePath;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $product->productPrice?->price ?? 0,
                    'image' => $imagePath ?? '/noimage/image.png',
                    'brand' => $product->brandRelation?->name ?? $product->brand,
                    'category' => $product->category?->name,
                ];
            });

        return response()->json([
            'brands' => $brands,
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    /**
     * Display the storefront homepage.
     */
    public function index(Request $request)
    {
        $this->initMembership();
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

        return Inertia::render('Storefront/Home', [
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'featuredProducts' => Inertia::defer(function () {
                $products = Product::with([
                    'category',
                    'seller',
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
                    ->activeAndNotExpired()
                    ->latest()
                    ->take(12)
                    ->get();

                $activePromotions = Promotion::with(['items'])
                    ->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->get();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                }

                return $products;
            }),
            'newProducts' => Inertia::defer(function () {
                $products = Product::with([
                    'category',
                    'seller',
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
                    ->activeAndNotExpired()
                    ->latest()
                    ->get();

                $activePromotions = Promotion::with(['items'])
                    ->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->get();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                }

                return $products;
            }),
            'bestSellerProducts' => Inertia::defer(function () {
                $bestSellerIds = DB::table('transaction_items')
                    ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
                    ->where('transactions.status', 'selesai')
                    ->groupBy('transaction_items.product_id')
                    ->selectRaw('transaction_items.product_id, SUM(transaction_items.quantity) as total_sold')
                    ->orderByDesc('total_sold')
                    ->take(10)
                    ->pluck('product_id')
                    ->all();

                $products = Product::with([
                    'category',
                    'seller',
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
                    ->activeAndNotExpired()
                    ->whereIn('id', $bestSellerIds)
                    ->get()
                    ->sortBy(fn ($p) => array_search($p->id, $bestSellerIds))
                    ->values();

                $activePromotions = Promotion::with(['items'])
                    ->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->get();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                }

                return $products;
            }),
            'activeFlashSale' => Inertia::defer(function () {
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

                return $activeFlashSale;
            }),
            'recentReviews' => Inertia::defer(fn () => ProductReview::with(['user', 'product.images', 'productVariant.options'])
                ->latest()
                ->take(8)
                ->get()),
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
    public function show($product)
    {
        $this->initMembership();
        if (! Str::isUuid($product)) {
            $product = Product::activeAndNotExpired()->where('slug', $product)->first();
            if (! $product) {
                abort(404);
            }
        } else {
            $product = Product::activeAndNotExpired()->where('id', $product)->firstOrFail();
        }

        $product->load([
            'seller.customerAddresses',
            'originAddress',
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

        if ($product->isListingExpired()) {
            abort(404, 'Produk tidak ditemukan atau telah habis masa aktifnya.');
        }

        // Calculate actual sold count from completed transactions
        $soldCount = DB::table('transaction_items')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transaction_items.product_id', $product->id)
            ->where('transactions.status', 'selesai')
            ->sum('transaction_items.quantity');

        $product->sold_count = (int) $soldCount;

        $relatedProducts = Product::with([
            'seller',
            'productPrice',
            'images',
            'category',
            'variants.productPrice',
            'variants.options',
            'variants.productStock',
            'variations.options',
        ])
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('listing_expires_at')
                    ->orWhere('listing_expires_at', '>', now());
            })
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
        $bundlingProductIds = [];
        foreach ($bundlingPromos as $promo) {
            $bundle = $promo->settings['bundle'] ?? null;
            if ($bundle) {
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

        $productsMap = [];
        if (! empty($bundlingProductIds)) {
            $productsMap = Product::with(['productPrice', 'images'])
                ->whereIn('id', $bundlingProductIds)
                ->get()
                ->keyBy('id');
        }

        $bundlingPromos->each(function ($promo) use ($productsMap) {
            $bundle = $promo->settings['bundle'];

            // Load buy_items products
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

            // Load get_items products
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
     * Helper to apply active promotions on a product and its variants.
     */
    private function applyPromotionsToProduct(Product $product, $activePromotions)
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

    /**
     * Display the storefront search/catalog listing page.
     */
    public function search(Request $request, ProductService $productService)
    {
        $this->initMembership();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Search', [
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'brands' => Inertia::defer(fn () => Brand::select('id', 'name', 'slug')
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()),
            'products' => Inertia::defer(fn () => $productService->getFilteredProducts($request, 36)),
            'filters' => [
                'q' => $request->input('q'),
                'category' => $request->input('category'),
                'brand' => $request->input('brand'),
                'sort' => $request->input('sort', 'latest'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'promo' => $request->boolean('promo'),
                'condition' => $request->input('condition', 'all'),
                'rating' => $request->input('rating', ''),
                'location' => $request->input('location', ''),
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
        $this->initMembership();
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'latest');

        $categories = Category::select('id', 'name', 'slug', 'image', 'icon')
            ->orderBy('order')
            ->get();

        // 1. Fetch active flash sale (supports member early access)
        $user = auth()->user();
        $earlyAccessMinutes = $this->membershipService->getFlashSaleEarlyAccessMinutes($user);

        $flashSaleQuery = Promotion::with([
            'items.product.productPrice',
            'items.product.images',
            'items.product.category',
            'items.variant.productPrice',
            'items.variant.options',
        ])
            ->where('type', 'flash_sale')
            ->where('is_active', true)
            ->where('end_time', '>=', now());

        if ($earlyAccessMinutes > 0) {
            // Member can access flash sale early
            $flashSaleQuery->where(function ($q) use ($earlyAccessMinutes) {
                $q->where('start_time', '<=', now())
                    ->orWhere(function ($q2) use ($earlyAccessMinutes) {
                        $q2->whereRaw("start_time - INTERVAL '?' MINUTE <= NOW()", [$earlyAccessMinutes])
                            ->where('member_early_access_minutes', '>', 0);
                    });
            });
        } else {
            $flashSaleQuery->where('start_time', '<=', now());
        }

        $activeFlashSale = $flashSaleQuery->latest()->first();

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
        } elseif ($sort === 'oldest') {
            $productsCollection = $productsCollection->sortBy('created_at');
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
        $perPage = 36;
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
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'brands' => Inertia::defer(fn () => Brand::select('id', 'name', 'slug')
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()),
            'products' => $productsPaginator,
            'activeFlashSale' => $activeFlashSale,
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
                'brand' => $request->input('brand'),
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
                'type' => $request->input('type', 'all'),
                'promo' => $request->boolean('promo'),
                'condition' => $request->input('condition', 'all'),
                'rating' => $request->input('rating', ''),
                'location' => $request->input('location', ''),
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the best sellers listing page.
     */
    public function produkTerlaris(Request $request, ProductService $productService)
    {
        $this->initMembership();
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'popular');

        // Build product query with full filter support
        $productsQuery = $productService->buildQuery($request);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/ProdukTerlaris', [
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'brands' => Inertia::defer(fn () => Brand::select('id', 'name', 'slug')
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()),
            'products' => Inertia::defer(function () use ($productsQuery, $minPrice, $maxPrice, $sort) {
                $productsCollection = $productsQuery->get();

                $activePromotions = Promotion::with(['items'])
                    ->where('is_active', true)
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->get();

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

                if ($sort === 'price_asc') {
                    $productsCollection = $productsCollection->sortBy(function ($p) {
                        return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
                    });
                } elseif ($sort === 'price_desc') {
                    $productsCollection = $productsCollection->sortByDesc(function ($p) {
                        return $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0);
                    });
                } elseif ($sort === 'oldest') {
                    $productsCollection = $productsCollection->sortBy('created_at');
                } elseif ($sort === 'latest') {
                    $productsCollection = $productsCollection->sortByDesc('created_at');
                } elseif ($sort === 'popular') {
                    $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
                    $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                        return $soldCounts[$p->id] ?? 0;
                    });
                } else {
                    $soldCounts = $this->getSoldCountsForProducts($productsCollection->pluck('id')->all());
                    $productsCollection = $productsCollection->sortByDesc(function ($p) use ($soldCounts) {
                        return $soldCounts[$p->id] ?? 0;
                    });
                }

                $page = request()->input('page', 1);
                $perPage = 36;
                $total = $productsCollection->count();

                $allProductIds = $productsCollection->pluck('id')->all();
                $soldCountsMap = $this->getSoldCountsForProducts($allProductIds);
                foreach ($productsCollection as $p) {
                    $p->sold_count = $soldCountsMap[$p->id] ?? 0;
                }

                $paginatedItems = $productsCollection->slice(($page - 1) * $perPage, $perPage)->values();

                return new LengthAwarePaginator(
                    $paginatedItems,
                    $total,
                    $perPage,
                    $page,
                    [
                        'path' => request()->url(),
                        'query' => request()->query(),
                    ]
                );
            }),
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
                'brand' => $request->input('brand'),
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort' => $sort,
                'type' => $request->input('type', 'all'),
                'promo' => $request->boolean('promo'),
                'condition' => $request->input('condition', 'all'),
                'rating' => $request->input('rating', ''),
                'location' => $request->input('location', ''),
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
    public function category(Request $request, ProductService $productService, ?string $category = null)
    {
        $this->initMembership();
        $categoryModel = null;

        if ($category) {
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $category);
            $categoryModel = $isUuid
                ? Category::where('id', $category)->first()
                : Category::where('slug', $category)->first();
        }

        if (! $categoryModel) {
            $categoryModel = Category::orderBy('order')->first() ?? Category::first();
        }

        // Pass category model ID to request filters if available
        if ($categoryModel) {
            $request->merge(['category' => $categoryModel->id]);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Category', [
            'category' => $categoryModel,
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'brands' => Inertia::defer(fn () => Brand::select('id', 'name', 'slug')
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()),
            'products' => Inertia::defer(fn () => $productService->getFilteredProducts($request, 36)),
            'filters' => [
                'q' => $request->input('q'),
                'brand' => $request->input('brand'),
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'sort' => $request->input('sort', 'latest'),
                'type' => $request->input('type', 'all'),
                'promo' => $request->boolean('promo'),
                'condition' => $request->input('condition', 'all'),
                'rating' => $request->input('rating', ''),
                'location' => $request->input('location', ''),
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
        if (config('app.is_seller') || $request->user()?->is_seller) {
            return redirect('/profile');
        }

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
            } elseif ($status === 'refund') {
                $query->whereHas('returns', function ($q) {
                    $q->where('type', 'refund');
                });
            } elseif ($status === 'retur') {
                $query->where(function ($q) {
                    $q->where('status', 'retur')
                        ->orWhereHas('returns', function ($r) {
                            $r->where('type', 'replacement');
                        });
                });
            }
        }

        $transactions = $query->with(['items.product', 'returns'])->latest()
            ->paginate(10)
            ->withQueryString();

        // Count for all statuses to display in the header tabs accurately
        $statusCounts = [
            'all' => Transaction::where('user_id', $request->user()->id)->count(),
            'belum_bayar' => Transaction::where('user_id', $request->user()->id)->where('status', 'belum_bayar')->count(),
            'berjalan' => Transaction::where('user_id', $request->user()->id)->whereIn('status', ['menunggu', 'diproses', 'dikemas', 'dikirim'])->count(),
            'selesai' => Transaction::where('user_id', $request->user()->id)->where('status', 'selesai')->count(),
            'batal' => Transaction::where('user_id', $request->user()->id)->where('status', 'batal')->count(),
            'refund' => Transaction::where('user_id', $request->user()->id)->whereHas('returns', function ($q) {
                $q->where('type', 'refund');
            })->count(),
            'retur' => Transaction::where('user_id', $request->user()->id)->where(function ($q) {
                $q->where('status', 'retur')->orWhereHas('returns', function ($r) {
                    $r->where('type', 'replacement');
                });
            })->count(),
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
                        $midtransOrderId = null;
                        if ($latestPayment) {
                            if ($latestPayment->gateway_response) {
                                $resp = is_string($latestPayment->gateway_response)
                                    ? json_decode($latestPayment->gateway_response, true)
                                    : $latestPayment->gateway_response;
                                $midtransOrderId = $resp['order_id'] ?? null;
                            }
                            if (! $midtransOrderId) {
                                // If gateway_transaction_id is UUID (contains dashes and is not trx number), we can try it,
                                // but if it is the trx number itself, that works too.
                                $midtransOrderId = $latestPayment->gateway_transaction_id;
                            }
                        }
                        if (! $midtransOrderId || strlen($midtransOrderId) < 5) {
                            $midtransOrderId = $transaction->transaction_number;
                        }

                        $midtransUrl = rtrim($apiUrl, '/').'/v2/'.$midtransOrderId.'/status';

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

        $midtransEnabled = config('app.midtrans_enabled', true) && Setting::where('key', 'midtrans_api_enabled')->value('value') === '1';
        $midtransEnabledMethods = $midtransEnabled ? MidtransService::getEnabledMethods() : [];

        return Inertia::render('Storefront/TransactionDetail', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'returnStatusLabels' => Transaction::returnStatusLabels(),
            'paymentMethods' => PaymentMethod::select('id', 'name', 'type')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'midtransEnabledMethods' => $midtransEnabledMethods,
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
            'midtrans_payment_type_key' => 'nullable|string|in:'.implode(',', array_keys(MidtransService::$paymentTypes)),
        ]);

        $paymentMethod = PaymentMethod::findOrFail($validated['payment_method_id']);
        $midtransPaymentTypeKey = $validated['midtrans_payment_type_key'] ?? null;

        $transaction->update([
            'payment_method_id' => $paymentMethod->id,
        ]);

        // If Midtrans Core API selected, create a new charge
        if ($midtransPaymentTypeKey && str_contains(strtolower($paymentMethod->name), 'midtrans')) {
            $user = $request->user();
            $result = MidtransService::charge(
                ($transaction->transaction_number ?? $transaction->id).'-'.time(),
                (int) $transaction->grand_total,
                $midtransPaymentTypeKey,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '',
                ],
            );

            if ($result['success']) {
                $instructions = $result['data'];
                // Update or create payment record
                $latestPayment = $transaction->payments()->latest()->first() ?? $transaction->payment;
                $gatewayResponse = ['_payment_instructions' => $instructions];
                if ($latestPayment) {
                    $latestPayment->update([
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                    ]);
                } else {
                    $transaction->payments()->create([
                        'amount' => $transaction->grand_total,
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                        'status' => 'pending',
                    ]);
                }
            } else {
                return redirect()->route('transactions.show', $transaction->id)
                    ->with('error', 'Metode pembayaran diubah tetapi gagal membuat charge Midtrans: '.($result['error'] ?? 'Unknown error'));
            }
        }

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
            'files.*' => [
                'required',
                'file',
                'mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webp',
                function ($attribute, $value, $fail) {
                    $isImage = str_starts_with($value->getMimeType(), 'image/');
                    if ($isImage && $value->getSize() > 2048 * 1024) {
                        $fail('Ukuran file gambar ulasan maksimal 2MB.');
                    } elseif (! $isImage && $value->getSize() > 20480 * 1024) {
                        $fail('Ukuran file video ulasan maksimal 20MB.');
                    }
                },
            ],
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
                $isImage = str_starts_with($file->getMimeType(), 'image/');
                if ($isImage) {
                    $path = ImageHelper::compressAndStore($file, 'reviews', 'public');
                } else {
                    $path = $file->store('reviews', 'public');
                }
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

    /**
     * Display the About Us page.
     */
    public function about(Request $request): Response
    {
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/About', [
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the Customer Digital Membership Card page.
     */
    public function membership(Request $request): Response
    {
        $levels = MembershipLevel::orderBy('order', 'asc')
            ->with('activeBenefits')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'order' => $l->order,
                'badge_color' => $l->badge_color,
                'icon' => $l->icon,
                'benefits' => $l->activeBenefits->map(fn ($b) => [
                    'label' => $b->label,
                    'icon' => $b->icon,
                    'type' => $b->type,
                    'value' => $b->value,
                ]),
            ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Membership', [
            'levels' => $levels,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display a seller's public storefront page.
     */
    public function sellerStore(Request $request, string $slug): Response
    {
        $isSellerEnabled = (bool) config('app.is_seller', false);

        if (! $isSellerEnabled) {
            abort(404);
        }

        $this->initMembership();

        $seller = User::where('store_slug', $slug)
            ->where('is_seller', true)
            ->firstOrFail();

        $sort = $request->input('sort', 'latest');
        $search = $request->input('q', '');

        $productsQuery = Product::with([
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
            ->where('user_id', $seller->id)
            ->activeAndNotExpired();

        if ($search) {
            $productsQuery->where('name', 'ilike', "%{$search}%");
        }

        match ($sort) {
            'price_asc' => $productsQuery->join('product_prices', 'products.id', '=', 'product_prices.product_id')->orderBy('product_prices.price', 'asc')->select('products.*'),
            'price_desc' => $productsQuery->join('product_prices', 'products.id', '=', 'product_prices.product_id')->orderBy('product_prices.price', 'desc')->select('products.*'),
            'popular' => $productsQuery->withCount(['transactionItems as sold_count' => fn ($q) => $q->whereHas('transaction', fn ($t) => $t->where('status', 'selesai'))])->orderByDesc('sold_count'),
            default => $productsQuery->latest(),
        };

        $products = $productsQuery->paginate(24)->withQueryString();

        // Apply promotions
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        foreach ($products as $product) {
            $this->applyPromotionsToProduct($product, $activePromotions);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/SellerStore', [
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'store_name' => $seller->store_name,
                'store_slug' => $seller->store_slug,
                'store_logo' => $seller->store_logo,
                'store_description' => $seller->store_description,
            ],
            'products' => $products,
            'filters' => [
                'q' => $search,
                'sort' => $sort,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
