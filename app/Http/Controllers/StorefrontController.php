<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AppliesProductPricing;
use App\Models\MembershipLevel;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    use AppliesProductPricing;

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

        event(new Registered($user));

        return redirect('/login')->with('success', 'Pendaftaran berhasil! Silakan periksa email Anda untuk memverifikasi akun sebelum masuk.');
    }

    /**
     * Display the About Us page.
     */
    public function about(Request $request): Response
    {
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/About', [
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display the Customer Digital Membership Card page.
     */
    public function membership(Request $request): Response
    {
        $levels = MembershipLevel::orderBy('order', 'asc')
            ->with('activeBenefits')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'order' => $l->order,
                'badge_color' => $l->badge_color,
                'icon' => $l->icon,
                'benefits' => $l->activeBenefits->map(fn ($b) => [
                    'label' => $b->label,
                    'icon' => $b->icon,
                    'type' => $b->type,
                    'value' => $b->value,
                ]),
            ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Membership', [
            'levels' => $levels,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display seller storefront page.
     */
    public function sellerStore(Request $request, string $slug): Response
    {
        $seller = User::whereRaw('LOWER(store_slug) = ?', [Str::lower($slug)])
            ->where('is_seller', true)
            ->first();

        if (! $seller) {
            $seller = $this->resolveLegacyStoreSlug($slug);
        }

        if (! $seller) {
            abort(404);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        $this->initMembership();

        $query = trim($request->input('q', ''));
        $sort = $request->input('sort', 'latest');

        $productsQuery = Product::with(ProductService::defaultEagerLoads())
            ->withAvg('reviews as avg_rating', 'rating')
            ->withCount('reviews as review_count')
            ->activeAndNotExpired()
            ->where('user_id', $seller->id);

        if ($query !== '') {
            $like = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
            $terms = array_filter(explode(' ', $query));
            $productsQuery->where(function ($q) use ($terms, $like) {
                foreach ($terms as $term) {
                    $q->where(function ($subQ) use ($term, $like) {
                        $subQ->where('products.name', $like, "%{$term}%")
                            ->orWhere('products.brand', $like, "%{$term}%")
                            ->orWhere('products.summary', $like, "%{$term}%")
                            ->orWhere('products.description', $like, "%{$term}%")
                            ->orWhereHas('category', fn ($qc) => $qc->where('name', $like, "%{$term}%"))
                            ->orWhereHas('brandRelation', fn ($qb) => $qb->where('name', $like, "%{$term}%"))
                            ->orWhereHas('brands', fn ($qb) => $qb->where('name', $like, "%{$term}%"));
                    });
                }
            });
        }

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $productsQuery->join('product_prices', function ($join) {
                $join->on('products.id', '=', 'product_prices.product_id')
                    ->whereNull('product_prices.product_variant_id');
            })
                ->orderBy('product_prices.price', $sort === 'price_asc' ? 'asc' : 'desc')
                ->orderBy('products.order', 'asc')
                ->orderByDesc('products.id')
                ->select('products.*');
        } else {
            $productsQuery->orderedLatest();
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 12;

        $products = $productsQuery->get();

        if ($sort === 'popular') {
            $soldCounts = $this->getSoldCountsForProducts($products->pluck('id')->all());
            $products = $products->sortByDesc(fn ($p) => $soldCounts[$p->id] ?? 0)->values();
        } elseif ($sort === 'price_asc') {
            $products = $products->sortBy(fn ($p) => $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0))->values();
        } elseif ($sort === 'price_desc') {
            $products = $products->sortByDesc(fn ($p) => $p->is_promo ? $p->promo_price : ($p->productPrice?->price ?? 0))->values();
        } else {
            $products = $products->sortByDesc('created_at')->values();
        }

        $activePromotions = $this->getActivePromotions();

        foreach ($products as $product) {
            $this->applyPromotionsToProduct($product, $activePromotions);
        }

        $paginator = new LengthAwarePaginator(
            $products->slice(($page - 1) * $perPage, $perPage)->values(),
            $products->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('Storefront/SellerStore', [
            'seller' => $seller,
            'products' => $paginator,
            'filters' => [
                'q' => $query,
                'sort' => $sort,
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Resolve legacy storefront links shaped `store-{name}-{uuid}` that were
     * generated as a frontend fallback before a seller had a real store_slug.
     */
    private function resolveLegacyStoreSlug(string $slug): ?User
    {
        if (! preg_match('/^store-.+?\-([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i', $slug, $matches)) {
            return null;
        }

        return User::where('id', $matches[1])
            ->where('is_seller', true)
            ->first();
    }
}
