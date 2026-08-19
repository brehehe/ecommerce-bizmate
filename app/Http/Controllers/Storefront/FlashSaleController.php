<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    use AppliesProductPricing;

    public function __construct(private readonly MembershipService $membershipService) {}

    /**
     * Display the flash sale listing page.
     */
    public function index(Request $request): Response
    {
        $this->initMembership();
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $sort = $request->input('sort', 'latest');

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
            $this->applyAdStatusToProduct($p);

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
}
