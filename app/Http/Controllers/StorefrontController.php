<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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
        ])
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
        ])
            ->where('active', true)
            ->latest()
            ->take(50)
            ->get();

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

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Home', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'newProducts' => $newProducts,
            'activeFlashSale' => $activeFlashSale,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display a single product detail page.
     */
    public function show(Product $product)
    {
        $product->load([
            'category',
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

        $relatedProducts = Product::with(['productPrice', 'images', 'category'])
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

        return Inertia::render('Storefront/Product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'storeName' => $storeName,
            'bundlingPromos' => $bundlingPromos->values(),
        ]);
    }

    /**
     * Helper to apply active promotions on a product and its variants.
     */
    private function applyPromotionsToProduct(Product $product, $activePromotions)
    {
        $basePrice = $product->productPrice?->price ?? 0;
        $appliedPromo = null;
        $appliedItem = null;

        // Search for matching promotions
        foreach ($activePromotions as $promo) {
            if ($promo->items->isEmpty()) {
                // Scope: all store products
                if (! $appliedPromo) {
                    $appliedPromo = $promo;
                }
            } else {
                // Scope: specific products
                $item = $promo->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
                if ($item) {
                    $appliedPromo = $promo;
                    $appliedItem = $item;
                    break;
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

                foreach ($activePromotions as $promo) {
                    if ($promo->items->isEmpty()) {
                        if (! $vAppliedPromo) {
                            $vAppliedPromo = $promo;
                        }
                    } else {
                        // Check if specific variant matches
                        $item = $promo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $item) {
                            $item = $promo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                        if ($item) {
                            $vAppliedPromo = $promo;
                            $vAppliedItem = $item;
                            break;
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

        return $product;
    }

    /**
     * Display the storefront search/catalog listing page.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        $categoryId = $request->input('category');
        $sort = $request->input('sort', 'relevance');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $promoOnly = $request->boolean('promo');

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
        ])
            ->where('active', true);

        $like = config('database.default') === 'sqlite' ? 'like' : 'ilike';

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
                    $q->whereIn('category_id', $uuids);
                }
                if (! empty($slugs)) {
                    $q->orWhereHas('category', function ($sub) use ($slugs) {
                        $sub->whereIn('slug', $slugs);
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
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                // Deterministic mock popularity score using crc32 of the product name
                return crc32($p->name) % 1000;
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
            'products' => $productsPaginator,
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
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
            }

            // Keyword filter
            if ($query) {
                $terms = array_filter(explode(' ', strtolower($query)));
                $nameLower = strtolower($p->name);
                $descLower = strtolower($p->description ?? '');
                $categoryNameLower = strtolower($p->category?->name ?? '');
                foreach ($terms as $term) {
                    if (! str_contains($nameLower, $term) &&
                        ! str_contains($descLower, $term) &&
                        ! str_contains($categoryNameLower, $term)) {
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
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return crc32($p->name) % 1000;
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
        ])
            ->where('active', true);

        $like = config('database.default') === 'sqlite' ? 'like' : 'ilike';

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
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return crc32($p->name) % 1000;
            });
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
                $productsCollection = $productsCollection->sortByDesc(function ($p) {
                    return crc32($p->name) % 1000;
                });
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
        ])
            ->where('active', true)
            ->where('category_id', $categoryModel->id);

        $like = config('database.default') === 'sqlite' ? 'like' : 'ilike';

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
            $productsCollection = $productsCollection->sortByDesc(function ($p) {
                return crc32($p->name) % 1000;
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

        Auth::login($user);

        $request->session()->regenerate();

        return redirect('/');
    }

    /**
     * Display customer transaction history.
     */
    public function transactionHistory(Request $request)
    {
        $transactions = Transaction::with([
            'paymentMethod:id,name,type',
            'payment',
            'items',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/TransactionHistory', [
            'transactions' => $transactions,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display a single transaction detail for the customer.
     */
    public function transactionDetail(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items',
            'payments',
            'payment',
        ]);

        // Auto-check Xendit invoice status if the transaction is still unpaid and is a gateway payment
        if ($transaction->status === 'belum_bayar' && $transaction->paymentMethod?->type === 'gateway') {
            $latestPayment = $transaction->payment;

            if ($latestPayment && $latestPayment->gateway_transaction_id) {
                try {
                    $invoiceId = $latestPayment->gateway_transaction_id;
                    $secretKey = $transaction->paymentMethod->api_secret ?: config('app.xendit.private_key');

                    if (empty($secretKey)) {
                        throw new \Exception('Xendit private key is not configured.');
                    }

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

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/TransactionDetail', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
