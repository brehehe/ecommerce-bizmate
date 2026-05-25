<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

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
        ]);

        $relatedProducts = Product::with(['productPrice', 'images'])
            ->where('active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(8)
            ->get();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return Inertia::render('Storefront/Product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'storeName' => $storeName,
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
}
