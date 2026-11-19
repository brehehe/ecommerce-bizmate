<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\RestoProfile;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use AppliesProductPricing;

    /**
     * Display the storefront homepage.
     */
    public function index(Request $request): Response
    {
        $this->initMembership();
        $storeSettings = Cache::remember('storefront_home_settings', 60, function () {
            return Setting::whereIn('key', [
                'store_name', 'store_logo', 'hero_banners',
                'side_banners', 'middle_wide_banner', 'popup_banner',
            ])->pluck('value', 'key');
        });

        $storeName = $storeSettings['store_name'] ?? config('app.name');
        $storeLogo = $storeSettings['store_logo'] ?? null;

        $heroBanners = ! empty($storeSettings['hero_banners']) ? json_decode($storeSettings['hero_banners'], true) : [];
        $sideBanners = ! empty($storeSettings['side_banners']) ? json_decode($storeSettings['side_banners'], true) : [];
        $middleWideBanner = ! empty($storeSettings['middle_wide_banner']) ? json_decode($storeSettings['middle_wide_banner'], true) : null;
        $popupBanner = ! empty($storeSettings['popup_banner']) ? json_decode($storeSettings['popup_banner'], true) : null;

        return Inertia::render('Storefront/Home', [
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'brands' => Inertia::defer(fn () => Brand::where('is_active', true)
                ->orderBy('order')
                ->select('id', 'name', 'slug')
                ->get()),
            'featuredProducts' => Inertia::defer(function () {
                $activeAdsMap = $this->getActiveAdsMap('home');
                $activePromotedIds = array_keys($activeAdsMap);

                $products = Product::with([
                    'category',
                    'brands',
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
                    ->orderedLatest()
                    ->take(12)
                    ->get();

                // If any promoted product is not in top 12, fetch and prepend it
                if (! empty($activePromotedIds)) {
                    $missingIds = array_diff($activePromotedIds, $products->pluck('id')->all());
                    if (! empty($missingIds)) {
                        $missingProducts = Product::with([
                            'category',
                            'brands',
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
                            ->whereIn('id', $missingIds)
                            ->get();

                        $products = $missingProducts->merge($products);
                    }
                }

                $activePromotions = $this->getActivePromotions();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                    $this->applyAdStatusToProduct($p, $activeAdsMap, 'home');
                }

                // Prioritize promoted products to the front
                $products = $products->sortByDesc(fn ($p) => ($p->is_promoted || $p->is_ad) ? 1 : 0)->values();

                return $products;
            }),
            'newProducts' => Inertia::defer(function () {
                $activeAdsMap = $this->getActiveAdsMap('home');
                $activePromotedIds = array_keys($activeAdsMap);

                $products = Product::with([
                    'category',
                    'brands',
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
                    ->orderedLatest()
                    ->take(24)
                    ->get();

                // If any promoted product is missing from active list, fetch and prepend it
                if (! empty($activePromotedIds)) {
                    $missingIds = array_diff($activePromotedIds, $products->pluck('id')->all());
                    if (! empty($missingIds)) {
                        $missingProducts = Product::with([
                            'category',
                            'brands',
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
                            ->whereIn('id', $missingIds)
                            ->get();

                        $products = $missingProducts->merge($products);
                    }
                }

                $activePromotions = $this->getActivePromotions();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                    $this->applyAdStatusToProduct($p, $activeAdsMap, 'home');
                }

                // Prioritize promoted products to the front
                $products = $products->sortByDesc(fn ($p) => ($p->is_promoted || $p->is_ad) ? 1 : 0)->values();

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

                $activeAdsMap = $this->getActiveAdsMap('home');
                $activePromotedIds = array_keys($activeAdsMap);
                $combinedIds = array_unique(array_merge($activePromotedIds, $bestSellerIds));

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
                    ->whereIn('id', $combinedIds)
                    ->get();

                $activePromotions = $this->getActivePromotions();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                    $this->applyAdStatusToProduct($p, $activeAdsMap, 'home');
                }

                $products = $products->sortByDesc(fn ($p) => ($p->is_promoted || $p->is_ad) ? 1 : 0)->values();

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
            'isFoodPick' => (bool) config('app.is_foodpick', false),
            'foodpickSettings' => config('app.foodpick', []),
            'restoPartners' => config('app.is_foodpick', false) ? RestoProfile::with(['user' => function ($q) {
                $q->select('id', 'name', 'store_name', 'store_slug', 'store_logo', 'store_description');
            }])
                ->withAvg(['reviews' => fn ($q) => $q->where('is_public', true)], 'rating')
                ->withCount(['reviews' => fn ($q) => $q->where('is_public', true)])
                ->where('approval_status', 'approved')
                ->orderByDesc('is_open_now')
                ->latest()
                ->get()
                ->map(function ($resto) {
                    $slug = $resto->slug ?: ($resto->user?->store_slug ?: Str::slug($resto->resto_name));

                    $firstProductImage = $resto->user_id ? Product::where('user_id', $resto->user_id)
                        ->with('images')
                        ->first()
                        ?->images
                        ->first()
                        ?->url : null;

                    $bannerUrl = $resto->banner_path
                        ? (str_starts_with($resto->banner_path, 'http') ? $resto->banner_path : ('/storage/'.ltrim($resto->banner_path, '/')))
                        : ($firstProductImage ?: '/images/default-resto-dish.jpg');

                    $avgRating = $resto->reviews_avg_rating !== null ? round((float) $resto->reviews_avg_rating, 1) : null;
                    $reviewCount = (int) ($resto->reviews_count ?? 0);

                    return [
                        'id' => $resto->id,
                        'user_id' => $resto->user_id,
                        'resto_name' => $resto->resto_name,
                        'slug' => $slug,
                        'culinary_category' => $resto->culinary_category ?: 'Aneka Kuliner',
                        'description' => $resto->description ?: ($resto->user?->store_description ?? ''),
                        'address' => $resto->address ?: '',
                        'latitude' => $resto->latitude,
                        'longitude' => $resto->longitude,
                        'is_open_now' => $resto->is_open_now !== false,
                        'avg_prep_time_minutes' => $resto->avg_prep_time_minutes ?: (int) config('app.foodpick.default_prep_minutes', 20),
                        'logo_url' => $resto->logo_path ? (str_starts_with($resto->logo_path, 'http') ? $resto->logo_path : ('/storage/'.ltrim($resto->logo_path, '/'))) : ($resto->user?->store_logo ? ('/storage/'.ltrim($resto->user->store_logo, '/')) : null),
                        'banner_url' => $bannerUrl,
                        'store_url' => '/'.$slug,
                        'rating' => $avgRating,
                        'review_count' => $reviewCount,
                    ];
                }) : [],
            'culinaryCategories' => config('app.is_foodpick', false) ? [
                ['name' => 'Aneka Ayam & Bebek', 'icon' => '🍗', 'slug' => 'aneka-ayam'],
                ['name' => 'Kopi & Minuman', 'icon' => '☕', 'slug' => 'kopi-minuman'],
                ['name' => 'Bakso, Mie & Soto', 'icon' => '🍜', 'slug' => 'bakso-mie'],
                ['name' => 'Cepat Saji & Burger', 'icon' => '🍔', 'slug' => 'cepat-saji'],
                ['name' => 'Aneka Nasi Nusantara', 'icon' => '🍚', 'slug' => 'aneka-nasi'],
                ['name' => 'Cemilan & Jajanan', 'icon' => '🥟', 'slug' => 'cemilan-jajanan'],
                ['name' => 'Seafood & Ikan Bakar', 'icon' => '🐟', 'slug' => 'seafood'],
                ['name' => 'Roti & Dessert Manis', 'icon' => '🍰', 'slug' => 'roti-kue'],
            ] : [],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'heroBanners' => $heroBanners,
            'sideBanners' => $sideBanners,
            'middleWideBanner' => $middleWideBanner,
            'popupBanner' => $popupBanner,
        ]);
    }
}
