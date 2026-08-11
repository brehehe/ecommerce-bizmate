<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use AppliesProductPricing;

    /**
     * Display a single product detail page.
     */
    public function show($product): Response
    {
        $this->initMembership();
        if (! Str::isUuid($product)) {
            $product = Product::activeAndNotExpired()->where('slug', $product)->first();
            if (! $product) {
                $product = Product::where('slug', $product)->firstOrFail();
            }
        } else {
            $product = Product::activeAndNotExpired()->where('id', $product)->first();
            if (! $product) {
                $product = Product::where('id', $product)->firstOrFail();
            }
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
}
