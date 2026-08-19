<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ProdukTerlarisController extends Controller
{
    use AppliesProductPricing;

    /**
     * Display the best sellers listing page.
     */
    public function index(Request $request, ProductService $productService): Response
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

                // Prioritize sponsored/ad products to the top
                $productsCollection = $productsCollection->sortByDesc(fn ($p) => ($p->is_promoted || $p->is_ad) ? 1 : 0);

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
}
