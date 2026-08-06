<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Get optimized eager loading relations with specific column selects.
     */
    public static function defaultEagerLoads(): array
    {
        return [
            'seller:id,name,store_name,store_slug,avatar,phone_number,email',
            'seller.customerAddresses' => function ($q) {
                $q->select([
                    'id', 'user_id', 'full_address', 'province_name',
                    'regency_name', 'district_name', 'village_name',
                    'postal_code', 'is_primary',
                ])->orderByDesc('is_primary');
            },
            'originAddress',
            'category:id,name,slug,icon,image',
            'categories:categories.id,name,slug',
            'brandRelation:id,name,slug',
            'brands:brands.id,name,slug',
            'productPrice:id,product_id,product_variant_id,price',
            'productStock:id,product_id,product_variant_id,stock',
            'images:id,product_id,path,is_main,sort_order',
            'variations:id,product_id,name',
            'variations.options:id,product_variation_id,name',
            'variants:id,product_id,sku,image',
            'variants.productPrice:id,product_id,product_variant_id,price',
            'variants.productStock:id,product_id,product_variant_id,stock',
            'variants.options:id,name',
        ];
    }

    /**
     * Build base product query with standard filters.
     */
    public function buildQuery(Request $request): Builder
    {
        $query = Product::with(self::defaultEagerLoads())
            ->activeAndNotExpired();

        $like = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';

        // Filter by keyword search (name, brand, summary, description, category, brand relations, SKU, variant options)
        if ($searchTerm = trim($request->input('q', ''))) {
            $terms = array_filter(explode(' ', $searchTerm));
            $query->where(function (Builder $q) use ($terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function (Builder $subQ) use ($term, $like) {
                        $subQ->where('name', $like, "%{$term}%")
                            ->orWhere('brand', $like, "%{$term}%")
                            ->orWhere('summary', $like, "%{$term}%")
                            ->orWhere('description', $like, "%{$term}%")
                            ->orWhereHas('brandRelation', fn ($qb) => $qb->where('name', $like, "%{$term}%"))
                            ->orWhereHas('brands', fn ($qb) => $qb->where('name', $like, "%{$term}%"))
                            ->orWhereHas('category', fn ($qc) => $qc->where('name', $like, "%{$term}%"))
                            ->orWhereHas('categories', fn ($qc) => $qc->where('name', $like, "%{$term}%"))
                            ->orWhereHas('variants', function ($qv) use ($term, $like) {
                                $qv->where('sku', $like, "%{$term}%")
                                    ->orWhereHas('options', fn ($qo) => $qo->where('name', $like, "%{$term}%"));
                            });
                    });
                }
            });
        }

        // Filter by category (array or string slug/uuid)
        if ($categoryId = $request->input('category')) {
            $categoryIds = is_array($categoryId) ? $categoryId : [$categoryId];
            $uuids = [];
            $slugs = [];

            foreach ($categoryIds as $cat) {
                if (is_string($cat)) {
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $cat)) {
                        $uuids[] = $cat;
                    } else {
                        $slugs[] = $cat;
                    }
                }
            }

            $query->where(function (Builder $q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('category_id', $uuids)
                        ->orWhereHas('categories', fn ($sub) => $sub->whereIn('categories.id', $uuids));
                }
                if (! empty($slugs)) {
                    $q->orWhereHas('category', fn ($sub) => $sub->whereIn('slug', $slugs))
                        ->orWhereHas('categories', fn ($sub) => $sub->whereIn('categories.slug', $slugs));
                }
            });
        }

        // Filter by brand
        if ($brandId = $request->input('brand')) {
            $brandIds = is_array($brandId) ? $brandId : [$brandId];
            $uuids = [];
            $slugs = [];

            foreach ($brandIds as $br) {
                if (is_string($br)) {
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $br)) {
                        $uuids[] = $br;
                    } else {
                        $slugs[] = $br;
                    }
                }
            }

            $query->where(function (Builder $q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('brand_id', $uuids)
                        ->orWhereHas('brands', fn ($sub) => $sub->whereIn('brands.id', $uuids));
                }
                if (! empty($slugs)) {
                    $q->orWhereIn('brand', $slugs)
                        ->orWhereHas('brandRelation', fn ($sub) => $sub->whereIn('slug', $slugs))
                        ->orWhereHas('brands', fn ($sub) => $sub->whereIn('brands.slug', $slugs));
                }
            });
        }

        // Filter by product type (physical vs digital)
        if ($type = $request->input('type')) {
            if ($type === 'physical') {
                $query->where('is_digital', false);
            } elseif ($type === 'digital') {
                $query->where('is_digital', true);
            }
        }

        // Filter by product condition (new / second / used / rent)
        if ($condition = $request->input('condition')) {
            if ($condition === 'second' || $condition === 'used') {
                $query->whereIn('condition', ['used', 'second']);
            } elseif (in_array($condition, ['new', 'rent'], true)) {
                $query->where('condition', $condition);
            }
        }

        // Filter by minimum rating (1..5)
        if ($rating = $request->input('rating')) {
            $minRating = (float) $rating;
            if ($minRating > 0 && $minRating <= 5) {
                $query->whereRaw('(SELECT COALESCE(AVG(rating), 0) FROM product_reviews WHERE product_reviews.product_id = products.id) >= ?', [$minRating]);
            }
        }

        // Filter by location (seller address, origin address, or default store city/province)
        if ($locationInput = trim($request->input('location', ''))) {
            $locations = array_filter(array_map('trim', explode(',', $locationInput)));
            if (! empty($locations)) {
                $storeCity = Setting::where('key', 'regency_name')->value('value') ?? '';
                $storeProvince = Setting::where('key', 'province_name')->value('value') ?? '';

                $query->where(function (Builder $q) use ($locations, $like, $storeCity, $storeProvince) {
                    foreach ($locations as $loc) {
                        $q->orWhereHas('seller.customerAddresses', function ($sub) use ($loc, $like) {
                            $sub->where('regency_name', $like, "%{$loc}%")
                                ->orWhere('province_name', $like, "%{$loc}%")
                                ->orWhere('district_name', $like, "%{$loc}%")
                                ->orWhere('full_address', $like, "%{$loc}%");
                        })->orWhereHas('originAddress', function ($sub) use ($loc, $like) {
                            $sub->where('regency_name', $like, "%{$loc}%")
                                ->orWhere('province_name', $like, "%{$loc}%")
                                ->orWhere('district_name', $like, "%{$loc}%")
                                ->orWhere('full_address', $like, "%{$loc}%");
                        });

                        if (($storeCity && str_contains(strtolower($storeCity), strtolower($loc))) ||
                            ($storeProvince && str_contains(strtolower($storeProvince), strtolower($loc)))) {
                            $q->orWhereDoesntHave('seller.customerAddresses');
                        }
                    }
                });
            }
        }

        return $query;
    }

    /**
     * Get paginated products with active promotions, price sorting & filters applied.
     */
    /**
     * Get paginated products with active promotions & SQL-level database pagination.
     */
    public function getFilteredProducts(Request $request, int $perPage = 36): LengthAwarePaginator
    {
        $query = $this->buildQuery($request);
        $sort = $request->input('sort', 'latest');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');
        $promoOnly = $request->boolean('promo');

        // Price range filter directly in SQL
        if ($minPrice || $maxPrice) {
            $query->whereHas('productPrice', function (Builder $pq) use ($minPrice, $maxPrice) {
                if ($minPrice) {
                    $pq->where('price', '>=', $minPrice);
                }
                if ($maxPrice) {
                    $pq->where('price', '<=', $maxPrice);
                }
            });
        }

        // Apply SQL ordering
        if ($sort === 'price_asc') {
            $query->join('product_prices', function ($join) {
                $join->on('products.id', '=', 'product_prices.product_id')
                    ->whereNull('product_prices.product_variant_id');
            })->orderBy('product_prices.price', 'asc')->select('products.*');
        } elseif ($sort === 'price_desc') {
            $query->join('product_prices', function ($join) {
                $join->on('products.id', '=', 'product_prices.product_id')
                    ->whereNull('product_prices.product_variant_id');
            })->orderBy('product_prices.price', 'desc')->select('products.*');
        } elseif ($sort === 'oldest') {
            $query->orderBy('products.created_at', 'asc');
        } else {
            $query->orderBy('products.created_at', 'desc');
        }

        // Execute SQL pagination (fetches ONLY $perPage items for the requested page)
        $paginator = $query->paginate($perPage);

        // Fetch active promotions
        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        // Apply promotions ONLY to the paginated page items
        foreach ($paginator->items() as $product) {
            $this->applyPromotionsToProduct($product, $activePromotions);
        }

        return $paginator;
    }

    /**
     * Helper to apply active promotion prices to a product object.
     */
    public function applyPromotionsToProduct(Product $product, $activePromotions): void
    {
        $productPromo = null;

        foreach ($activePromotions as $promo) {
            foreach ($promo->items as $item) {
                if ($item->product_id === $product->id) {
                    $productPromo = [
                        'promo_price' => $item->promo_price,
                        'discount_percentage' => $item->discount_percentage,
                        'promo_name' => $promo->name,
                        'promo_end_time' => $promo->end_time,
                    ];
                    break 2;
                }
            }
        }

        if ($productPromo) {
            $product->is_promo = true;
            $product->promo_price = (float) $productPromo['promo_price'];
            $product->discount_percentage = (int) $productPromo['discount_percentage'];
            $product->promo_name = $productPromo['promo_name'];
            $product->promo_end_time = $productPromo['promo_end_time'];
        } else {
            $product->is_promo = false;
            $product->promo_price = null;
            $product->discount_percentage = 0;
        }
    }
}
