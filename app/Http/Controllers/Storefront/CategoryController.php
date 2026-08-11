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

class CategoryController extends Controller
{
    use AppliesProductPricing;

    /**
     * Display the products in a specific category.
     */
    public function index(Request $request, ProductService $productService, ?string $category = null): Response
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
}
