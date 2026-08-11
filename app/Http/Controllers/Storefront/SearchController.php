<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class SearchController extends Controller
{
    use AppliesProductPricing;

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
     * Display the storefront search/catalog listing page.
     */
    public function search(Request $request, ProductService $productService): Response
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
}
