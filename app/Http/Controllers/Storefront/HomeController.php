<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

                $activePromotions = $this->getActivePromotions();

                foreach ($products as $p) {
                    $this->applyPromotionsToProduct($p, $activePromotions);
                }

                return $products;
            }),
            'newProducts' => Inertia::defer(function () {
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
                    ->get();

                $activePromotions = $this->getActivePromotions();

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

                $activePromotions = $this->getActivePromotions();

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
}
