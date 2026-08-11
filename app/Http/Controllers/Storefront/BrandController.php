<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Setting;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    use AppliesProductPricing;

    /**
     * Display storefront brands page.
     */
    public function index(Request $request, ProductService $productService, ?string $brand = null): Response
    {
        $this->initMembership();
        $brandModel = null;

        if ($brand) {
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $brand);
            $brandModel = $isUuid
                ? Brand::where('id', $brand)->first()
                : Brand::where('slug', $brand)->first();
        }

        if (! $brandModel) {
            $brandModel = [
                'id' => '',
                'name' => 'Semua Brand',
                'slug' => '',
            ];
        } else {
            $request->merge(['brand' => $brandModel->id]);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Brands', [
            'brand' => $brandModel,
            'brands' => Inertia::defer(fn () => Brand::withCount('products')
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('name')
                ->get()),
            'categories' => Inertia::defer(fn () => Category::select('id', 'name', 'slug', 'image', 'icon')
                ->orderBy('order')
                ->get()),
            'products' => Inertia::defer(fn () => $productService->getFilteredProducts($request, 36)),
            'filters' => [
                'q' => $request->input('q'),
                'brand' => $request->input('brand'),
                'category' => $request->input('category'),
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
}
