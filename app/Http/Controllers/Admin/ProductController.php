<?php

namespace App\Http\Controllers\Admin;

use App\Events\ListingPaymentConfirmed;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ImportProductsJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductListingPayment;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use App\Models\Setting;
use App\Models\User;
use App\Services\MidtransService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = Product::query();

        if ($request->user() && $request->user()->is_seller && ! $request->user()->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->where('user_id', $request->user()->id);
        }

        $sort = $request->get('sort', 'order-asc');
        switch ($sort) {
            case 'order-asc':
                $query->orderBy('order', 'asc')->latest();
                break;
            case 'order-desc':
                $query->orderBy('order', 'desc')->latest();
                break;
            case 'name-asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name-desc':
                $query->orderBy('name', 'desc');
                break;
            case 'price-asc':
                $query->leftJoin('product_prices', function ($join) {
                    $join->on('products.id', '=', 'product_prices.product_id')
                        ->whereNull('product_prices.product_variant_id');
                })
                    ->select('products.*')
                    ->orderBy('product_prices.price', 'asc');
                break;
            case 'price-desc':
                $query->leftJoin('product_prices', function ($join) {
                    $join->on('products.id', '=', 'product_prices.product_id')
                        ->whereNull('product_prices.product_variant_id');
                })
                    ->select('products.*')
                    ->orderBy('product_prices.price', 'desc');
                break;
            case 'stock-asc':
                $query->leftJoin('product_stocks', function ($join) {
                    $join->on('products.id', '=', 'product_stocks.product_id')
                        ->whereNull('product_stocks.product_variant_id');
                })
                    ->select('products.*')
                    ->orderBy('product_stocks.stock', 'asc');
                break;
            case 'stock-desc':
                $query->leftJoin('product_stocks', function ($join) {
                    $join->on('products.id', '=', 'product_stocks.product_id')
                        ->whereNull('product_stocks.product_variant_id');
                })
                    ->select('products.*')
                    ->orderBy('product_stocks.stock', 'desc');
                break;
            case 'latest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            default:
                $query->orderBy('order', 'asc')->latest();
                break;
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('sku', $likeOperator, "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->get('category') !== 'all') {
            $categoryParam = $request->get('category');
            $categoryIds = is_array($categoryParam) ? $categoryParam : explode(',', $categoryParam);
            $categoryIds = array_filter(array_map('trim', $categoryIds));
            if (! empty($categoryIds)) {
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }
        }

        if ($request->filled('brand') && $request->get('brand') !== 'all') {
            $brandParam = $request->get('brand');
            $brandIds = is_array($brandParam) ? $brandParam : explode(',', $brandParam);
            $brandIds = array_filter(array_map('trim', $brandIds));
            if (! empty($brandIds)) {
                $query->whereHas('brands', function ($q) use ($brandIds) {
                    $q->whereIn('brands.id', $brandIds);
                });
            }
        }

        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('active', $request->get('status') === 'active');
        }

        $perPage = $request->get('per_page', 10);
        $limit = $perPage === 'all' ? 999999 : (int) $perPage;
        $products = $query->paginate($limit)->withQueryString();

        if (! $products->getCollection()->isEmpty()) {
            $products->getCollection()->load([
                'user',
                'category',
                'categories',
                'brandRelation',
                'brands',
                'productPrice',
                'productStock',
                'tierPrices',
                'variants' => function ($v) {
                    $v->withSum(['transactionItems as total_qty_sold' => function ($q) {
                        $q->whereHas('transaction', function ($t) {
                            $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
                        });
                    }], 'quantity')
                        ->withSum(['transactionItems as total_revenue' => function ($q) {
                            $q->whereHas('transaction', function ($t) {
                                $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
                            });
                        }], 'subtotal')
                        ->withSum(['returnItems as total_qty_returned' => function ($q) {
                            $q->whereHas('returnRequest', function ($r) {
                                $r->where('status', 'selesai');
                            });
                        }], 'quantity_returned')
                        ->withSum(['returnItems as total_refund_amount' => function ($q) {
                            $q->whereHas('returnRequest', function ($r) {
                                $r->where('status', 'selesai');
                            });
                        }], 'refund_subtotal');
                },
                'variants.options',
                'variants.productPrice',
                'variants.productStock',
                'variants.tierPrices',
                'variations.options',
            ]);

            $products->getCollection()->loadSum(['transactionItems as total_qty_sold' => function ($q) {
                $q->whereHas('transaction', function ($t) {
                    $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
                });
            }], 'quantity');

            $products->getCollection()->loadSum(['transactionItems as total_revenue' => function ($q) {
                $q->whereHas('transaction', function ($t) {
                    $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
                });
            }], 'subtotal');

            $products->getCollection()->loadSum(['returnItems as total_qty_returned' => function ($q) {
                $q->whereHas('returnRequest', function ($r) {
                    $r->where('status', 'selesai');
                });
            }], 'quantity_returned');

            $products->getCollection()->loadSum(['returnItems as total_refund_amount' => function ($q) {
                $q->whereHas('returnRequest', function ($r) {
                    $r->where('status', 'selesai');
                });
            }], 'refund_subtotal');
        }

        $products->getCollection()->transform(function ($product) {
            $sold = (int) ($product->total_qty_sold ?? 0) - (int) ($product->total_qty_returned ?? 0);
            $revenue = (float) ($product->total_revenue ?? 0) - (float) ($product->total_refund_amount ?? 0);

            $product->performance_sold = max(0, $sold);
            $product->performance_revenue = max(0.00, $revenue);

            if ($product->variants) {
                foreach ($product->variants as $variant) {
                    $vSold = (int) ($variant->total_qty_sold ?? 0) - (int) ($variant->total_qty_returned ?? 0);
                    $vRevenue = (float) ($variant->total_revenue ?? 0) - (float) ($variant->total_refund_amount ?? 0);

                    $variant->performance_sold = max(0, $vSold);
                    $variant->performance_revenue = max(0.00, $vRevenue);
                }
            }

            return $product;
        });

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::select('id', 'name')->orderBy('name')->get();

        $categoryFilter = $request->get('category', []);
        $categoryFilter = is_array($categoryFilter) ? $categoryFilter : explode(',', $categoryFilter);
        $categoryFilter = array_filter(array_map('trim', $categoryFilter));

        $brandFilter = $request->get('brand', []);
        $brandFilter = is_array($brandFilter) ? $brandFilter : explode(',', $brandFilter);
        $brandFilter = array_filter(array_map('trim', $brandFilter));

        $isSellerMode = (bool) config('app.is_seller', false);
        $listingPricing = $this->getListingPricingConfig();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'import_auto_fetch_images' => (bool) config('services.products.import_auto_fetch_images', true),
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
            'isSellerMode' => $isSellerMode,
            'listingPricing' => $listingPricing,
            'filters' => [
                'search' => $request->get('search', ''),
                'category' => array_values($categoryFilter),
                'brand' => array_values($brandFilter),
                'status' => $request->get('status', 'all'),
                'sort' => $sort,
                'per_page' => $request->get('per_page', '10'),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        $isSellerMode = (bool) config('app.is_seller', false);
        $listingPricing = $this->getListingPricingConfig();

        $isAdmin = $request->user()?->hasAnyRole(['Super Admin', 'Admin']) || ! $request->user()?->is_seller;
        $sellers = [];
        if ($isAdmin) {
            $sellers = User::select('id', 'name', 'email', 'phone_number', 'store_name', 'is_seller')
                ->with('customerAddresses')
                ->orderBy('name')
                ->get();
        }

        $prefix = 'PRD-'.date('Ymd').'-';
        $count = 1;
        do {
            $suggestedSku = $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            $count++;
        } while (Product::where('sku', $suggestedSku)->exists() || ProductVariant::where('sku', $suggestedSku)->exists());

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
            'brands' => $brands,
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
            'isSellerMode' => $isSellerMode,
            'listingPricing' => $listingPricing,
            'suggestedSku' => $suggestedSku,
            'isAdmin' => $isAdmin,
            'sellers' => $sellers,
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Product store request payload', $request->all());

        if ($request->has('category_id') && ! $request->has('category_ids')) {
            $request->merge(['category_ids' => [$request->input('category_id')]]);
        }
        if ($request->has('brand_id') && ! $request->has('brand_ids')) {
            $request->merge(['brand_ids' => array_filter([$request->input('brand_id')])]);
        }

        if ($request->has('user_id') && (! $request->input('user_id') || ! Str::isUuid($request->input('user_id')))) {
            $request->merge(['user_id' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'brand' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'is_unlimited' => 'boolean',
            'is_digital' => 'boolean',
            'is_exclusive' => 'boolean',
            'exclusive_min_level_order' => 'nullable|integer|min:0',
            'is_early_access' => 'boolean',
            'early_access_until' => 'nullable|date',
            'early_access_min_level_order' => 'nullable|integer|min:0',
            'stock_status' => 'nullable|string',
            'condition' => 'nullable|string|in:new,used,second,rent',
            'summary' => 'nullable|string|max:255',
            'description' => 'required|string',
            'specifications' => 'nullable|array',
            'size_chart' => 'nullable|array',
            'weight' => 'nullable|integer|min:0',
            'length' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'photos' => 'nullable|array',
            'variations' => 'nullable|array',
            'variants' => 'nullable|array',
            'tier_prices' => 'nullable|array',
            'tier_prices.*.min_qty' => 'required|integer|min:2',
            'tier_prices.*.price' => 'required|numeric|min:0',
            'video_url' => 'nullable|string',
            'video_file' => 'nullable|file|mimes:mp4,mov,webm,qt|max:10240',
            'model_3d_url' => 'nullable|string',
            'model_3d_file' => 'nullable|file|max:10240',
            'model_3d_usdz_url' => 'nullable|string',
            'model_3d_usdz_file' => 'nullable|file|max:10240',
        ]);

        $this->validateBase64Images($request);

        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];

        $validated['category_id'] = head($categoryIds) ?: null;
        $validated['brand_id'] = head($brandIds) ?: null;

        if (! empty($validated['brand_id'])) {
            $validated['brand'] = Brand::find($validated['brand_id'])?->name;
        } else {
            $validated['brand'] = null;
        }

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $validated['stock'] = $validated['stock'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['length'] = $validated['length'] ?? 0;
        $validated['width'] = $validated['width'] ?? 0;
        $validated['height'] = $validated['height'] ?? 0;

        // Process file uploads or manual URLs
        $videoPath = $request->input('video_url');
        if ($request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('products/videos', 'public');
            $videoPath = 'storage/'.$path;
        }
        $validated['video_path'] = $videoPath;

        $modelPath = $request->input('model_3d_url');
        if ($request->hasFile('model_3d_file')) {
            $filename = Str::random(40).'.glb';
            $path = $request->file('model_3d_file')->storeAs('products/models', $filename, 'public');
            $modelPath = 'storage/'.$path;
        }
        $validated['model_3d_path'] = $modelPath;

        $usdzPath = $request->input('model_3d_usdz_url');
        if ($request->hasFile('model_3d_usdz_file')) {
            $filename = Str::random(40).'.usdz';
            $path = $request->file('model_3d_usdz_file')->storeAs('products/models', $filename, 'public');
            $usdzPath = 'storage/'.$path;
        }
        $validated['model_3d_usdz_path'] = $usdzPath;

        // Remove price/stock/file fields from product creation array
        $productData = Arr::except($validated, [
            'price',
            'cost',
            'stock',
            'min_stock',
            'min_purchase',
            'is_unlimited',
            'photos',
            'variations',
            'variants',
            'tier_prices',
            'category_ids',
            'brand_ids',
            'video_url',
            'video_file',
            'model_3d_url',
            'model_3d_file',
            'model_3d_usdz_url',
            'model_3d_usdz_file',
            'new_seller',
        ]);

        $isAdmin = $request->user()?->hasAnyRole(['Super Admin', 'Admin']) || ! $request->user()?->is_seller;

        if ($isAdmin && ! empty($request->input('new_seller.name'))) {
            $newUser = $this->findOrCreateSeller($request->input('new_seller'));
            $productData['user_id'] = $newUser->id;
            if (empty($productData['contact_name'])) {
                $productData['contact_name'] = $newUser->name;
            }
            if (empty($productData['contact_phone'])) {
                $productData['contact_phone'] = $newUser->phone_number;
            }
        } elseif ($isAdmin && $request->filled('user_id')) {
            $productData['user_id'] = $request->input('user_id');
        } else {
            $productData['user_id'] = $request->user()?->id;
        }

        $isSellerMode = (bool) config('app.is_seller', false);
        if ($isSellerMode && $request->user()?->is_seller) {
            $config = $this->getListingPricingConfig();
            $calc = $this->calculateListingFeeAndDays($request, $config);

            $productData['listing_expires_at'] = now()->addDays($calc['days']);
            $productData['listing_fee'] = $calc['fee'];
            $productData['listing_days'] = $calc['days'];
        } else {
            $productData['listing_expires_at'] = null;
            $productData['listing_fee'] = 0;
            $productData['listing_days'] = 0;
        }

        $rawSku = trim($validated['sku'] ?? '');
        if (empty($rawSku)) {
            $rawSku = Str::upper(Str::slug($validated['name']));
        }
        $sku = $rawSku;
        $count = 1;
        while (Product::where('sku', $sku)->exists()) {
            $sku = $rawSku.'-'.$count;
            $count++;
        }
        $productData['sku'] = $sku;

        $product = Product::create($productData);

        // Sync many-to-many relationships
        $product->categories()->sync($categoryIds);
        $product->brands()->sync($brandIds);

        // Create Master Price
        $product->productPrice()->create([
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? null,
        ]);

        // Create Master Stock
        $product->productStock()->create([
            'stock' => $validated['stock'],
            'min_stock' => $validated['min_stock'] ?? 0,
            'min_purchase' => $validated['min_purchase'] ?? 1,
            'is_unlimited' => $validated['is_unlimited'] ?? false,
        ]);

        // Create Tier Prices for Master Product
        if (! empty($validated['tier_prices'])) {
            foreach ($validated['tier_prices'] as $tp) {
                $product->tierPrices()->create([
                    'min_qty' => $tp['min_qty'],
                    'price' => $tp['price'],
                ]);
            }
        }
        // Process photos
        if (! empty($validated['photos'])) {
            foreach ($validated['photos'] as $index => $photoBase64) {
                if (preg_match('/^data:image\/(\w+);base64,/', $photoBase64, $type)) {
                    $photoBase64 = substr($photoBase64, strpos($photoBase64, ',') + 1);
                    $type = strtolower($type[1]);
                    $photoBase64 = base64_decode(str_replace(' ', '+', $photoBase64));
                    $photoBase64 = ImageHelper::compress($photoBase64, $type, 75);
                    $filename = 'product_'.$product->id.'_'.time().'_'.$index.'.'.$type;
                    Storage::disk('public')->put('products/'.$filename, $photoBase64);

                    $product->images()->create([
                        'path' => 'storage/products/'.$filename,
                        'is_main' => $index === 0,
                        'sort_order' => $index,
                    ]);

                    if ($index === 0) {
                        $product->update(['image' => 'storage/products/'.$filename]);
                    }
                }
            }
        }

        // Process variations
        $variationsInput = $request->input('variations', []);
        $variantsInput = $request->input('variants', []);

        if (! empty($variationsInput)) {
            $variationMap = [];
            foreach ($variationsInput as $vIndex => $vData) {
                $variation = $product->variations()->create([
                    'name' => $vData['name'],
                    'sort_order' => $vIndex,
                ]);
                foreach ($vData['options'] as $oIndex => $optData) {
                    $imagePath = null;
                    if (! empty($optData['image']) && preg_match('/^data:image\/(\w+);base64,/', $optData['image'], $type)) {
                        $imgBase64 = substr($optData['image'], strpos($optData['image'], ',') + 1);
                        $type = strtolower($type[1]);
                        $imgBase64 = base64_decode(str_replace(' ', '+', $imgBase64));
                        $imgBase64 = ImageHelper::compress($imgBase64, $type, 75);
                        $filename = 'opt_'.$product->id.'_'.time().'_'.uniqid().'.'.$type;
                        Storage::disk('public')->put('products/'.$filename, $imgBase64);
                        $imagePath = 'storage/products/'.$filename;
                    }

                    $option = $variation->options()->create([
                        'name' => $optData['name'],
                        'description' => $optData['description'] ?? null,
                        'image' => $imagePath,
                        'sort_order' => $oIndex,
                    ]);
                    $variationMap[$optData['id']] = $option->id;
                }
            }
            if (! empty($variantsInput)) {
                foreach ($variantsInput as $vCombData) {
                    $hasCustomWeight = ! empty($vCombData['is_custom']) && ! empty($vCombData['custom_weight']);
                    $rawVariantSku = trim($vCombData['sku'] ?? '');
                    if (empty($rawVariantSku)) {
                        $rawVariantSku = $product->sku.'-VAR-'.Str::random(4);
                    }
                    $variantSku = $rawVariantSku;
                    $vCount = 1;
                    while (ProductVariant::where('sku', $variantSku)->exists()) {
                        $variantSku = $rawVariantSku.'-'.$vCount;
                        $vCount++;
                    }

                    $variant = $product->variants()->create([
                        'sku' => $variantSku,
                        'weight' => $hasCustomWeight ? ($vCombData['weight'] ?: null) : null,
                        'length' => $hasCustomWeight ? ($vCombData['length'] ?: null) : null,
                        'width' => $hasCustomWeight ? ($vCombData['width'] ?: null) : null,
                        'height' => $hasCustomWeight ? ($vCombData['height'] ?: null) : null,
                    ]);

                    // Custom Variant Price
                    if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_price'])) {
                        $variant->productPrice()->create([
                            'product_id' => $product->id,
                            'price' => $vCombData['price'] ?: 0,
                            'cost' => $vCombData['cost'] ?: null,
                        ]);

                        // Custom Variant Tier Prices
                        if (! empty($vCombData['tier_prices'])) {
                            foreach ($vCombData['tier_prices'] as $tp) {
                                $variant->tierPrices()->create([
                                    'product_id' => $product->id,
                                    'min_qty' => $tp['min_qty'],
                                    'price' => $tp['price'],
                                ]);
                            }
                        }
                    }

                    // Custom Variant Stock
                    if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_stock'])) {
                        $variant->productStock()->create([
                            'product_id' => $product->id,
                            'stock' => $vCombData['stock'] ?: 0,
                            'min_stock' => $vCombData['min_stock'] ?: 0,
                            'min_purchase' => $vCombData['min_purchase'] ?: 1,
                            'is_unlimited' => ! empty($vCombData['is_unlimited']),
                        ]);
                    }

                    // Precise option matching
                    $frontIds = explode('_', $vCombData['id']);
                    $optionIdsToAttach = [];
                    $variantImage = null;
                    foreach ($variationMap as $frontId => $dbId) {
                        if (in_array((string) $frontId, $frontIds, true)) {
                            $optionIdsToAttach[] = $dbId;
                            // Find the first option with a saved image to use as the variant image
                            $dbOption = ProductVariationOption::find($dbId);
                            if ($dbOption && $dbOption->image && ! $variantImage) {
                                $variantImage = $dbOption->image;
                            }
                        }
                    }
                    $variant->options()->attach($optionIdsToAttach);
                    if ($variantImage) {
                        $variant->update(['image' => $variantImage]);
                    }
                }
            }
        }

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function show(Product $product)
    {
        return redirect()->route('admin.products.edit', $product);
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $categories = Category::select('id', 'name')->get();
        $brands = Brand::orderBy('name')->get();

        $isAdmin = $request->user()?->hasAnyRole(['Super Admin', 'Admin']) || ! $request->user()?->is_seller;
        $sellers = [];
        if ($isAdmin) {
            $sellers = User::select('id', 'name', 'email', 'phone_number', 'store_name', 'is_seller')
                ->with('customerAddresses')
                ->orderBy('name')
                ->get();
        }

        $product->load([
            'images',
            'productPrice',
            'productStock',
            'tierPrices',
            'variations.options',
            'variants.options',
            'variants.productPrice',
            'variants.productStock',
            'variants.tierPrices',
            'categories',
            'brands',
            'user',
            'seller.customerAddresses',
        ]);

        $isSellerMode = (bool) config('app.is_seller', false);
        $listingPricing = $this->getListingPricingConfig();

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
            'isSellerMode' => $isSellerMode,
            'listingPricing' => $listingPricing,
            'isAdmin' => $isAdmin,
            'sellers' => $sellers,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        Log::info('Product update request payload', $request->all());

        if ($request->has('category_id') && ! $request->has('category_ids')) {
            $request->merge(['category_ids' => [$request->input('category_id')]]);
        }
        if ($request->has('brand_id') && ! $request->has('brand_ids')) {
            $request->merge(['brand_ids' => array_filter([$request->input('brand_id')])]);
        }

        if ($request->has('user_id') && (! $request->input('user_id') || ! Str::isUuid($request->input('user_id')))) {
            $request->merge(['user_id' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'exists:brands,id',
            'brand' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'is_unlimited' => 'boolean',
            'is_digital' => 'boolean',
            'is_exclusive' => 'boolean',
            'exclusive_min_level_order' => 'nullable|integer|min:0',
            'is_early_access' => 'boolean',
            'early_access_until' => 'nullable|date',
            'early_access_min_level_order' => 'nullable|integer|min:0',
            'stock_status' => 'nullable|string',
            'condition' => 'nullable|string|in:new,used,second,rent',
            'price_type' => 'nullable|string|in:net,nego',
            'usage_period' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
            'new_seller' => 'nullable|array',
            'new_seller.name' => 'nullable|string|max:255',
            'new_seller.phone_number' => 'nullable|string|max:50',
            'new_seller.email' => 'nullable|email|max:255',
            'new_seller.address' => 'nullable|string|max:500',
            'edit_seller' => 'nullable|array',
            'edit_seller.name' => 'nullable|string|max:255',
            'edit_seller.phone_number' => 'nullable|string|max:50',
            'edit_seller.email' => 'nullable|email|max:255',
            'edit_seller.store_name' => 'nullable|string|max:255',
            'edit_seller.address' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:255',
            'description' => 'required|string',
            'specifications' => 'nullable|array',
            'size_chart' => 'nullable|array',
            'weight' => 'nullable|integer|min:0',
            'length' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'photos' => 'nullable|array',
            'variations' => 'nullable|array',
            'variants' => 'nullable|array',
            'tier_prices' => 'nullable|array',
            'tier_prices.*.min_qty' => 'required|integer|min:2',
            'tier_prices.*.price' => 'required|numeric|min:0',
            'video_url' => 'nullable|string',
            'video_file' => 'nullable|file|mimes:mp4,mov,webm,qt|max:10240',
            'model_3d_url' => 'nullable|string',
            'model_3d_file' => 'nullable|file|max:10240',
            'model_3d_usdz_url' => 'nullable|string',
            'model_3d_usdz_file' => 'nullable|file|max:10240',
        ]);

        $this->validateBase64Images($request);

        $categoryIds = $validated['category_ids'] ?? [];
        $brandIds = $validated['brand_ids'] ?? [];

        $validated['category_id'] = head($categoryIds) ?: null;
        $validated['brand_id'] = head($brandIds) ?: null;

        if (! empty($validated['brand_id'])) {
            $validated['brand'] = Brand::find($validated['brand_id'])?->name;
        } else {
            $validated['brand'] = null;
        }

        if ($product->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
        }
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $validated['stock'] = $validated['stock'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['length'] = $validated['length'] ?? 0;
        $validated['width'] = $validated['width'] ?? 0;
        $validated['height'] = $validated['height'] ?? 0;

        // Process file uploads or manual URLs for interactive media
        $videoPath = $request->input('video_url', $product->video_path);
        if ($request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('products/videos', 'public');
            $videoPath = 'storage/'.$path;
        }
        $validated['video_path'] = $videoPath;

        $modelPath = $request->input('model_3d_url', $product->model_3d_path);
        if ($request->hasFile('model_3d_file')) {
            $filename = Str::random(40).'.glb';
            $path = $request->file('model_3d_file')->storeAs('products/models', $filename, 'public');
            $modelPath = 'storage/'.$path;
        }
        $validated['model_3d_path'] = $modelPath;

        $usdzPath = $request->input('model_3d_usdz_url', $product->model_3d_usdz_path);
        if ($request->hasFile('model_3d_usdz_file')) {
            $filename = Str::random(40).'.usdz';
            $path = $request->file('model_3d_usdz_file')->storeAs('products/models', $filename, 'public');
            $usdzPath = 'storage/'.$path;
        }
        $validated['model_3d_usdz_path'] = $usdzPath;

        // Extract products fields
        $productData = Arr::except($validated, [
            'price',
            'cost',
            'stock',
            'min_stock',
            'min_purchase',
            'is_unlimited',
            'variations',
            'variants',
            'photos',
            'tier_prices',
            'category_ids',
            'brand_ids',
            'video_url',
            'video_file',
            'model_3d_url',
            'model_3d_file',
            'model_3d_usdz_url',
            'model_3d_usdz_file',
            'new_seller',
            'edit_seller',
        ]);

        $isAdmin = $request->user()?->hasAnyRole(['Super Admin', 'Admin']) || ! $request->user()?->is_seller;

        if ($isAdmin) {
            if (! empty($request->input('new_seller.name'))) {
                $newUser = $this->findOrCreateSeller($request->input('new_seller'));
                $productData['user_id'] = $newUser->id;
                if (empty($productData['contact_name'])) {
                    $productData['contact_name'] = $newUser->name;
                }
                if (empty($productData['contact_phone'])) {
                    $productData['contact_phone'] = $newUser->phone_number;
                }
            } elseif ($request->filled('user_id')) {
                $productData['user_id'] = $request->input('user_id');
            }

            // Process edit_seller inline profile updates
            if (! empty($request->input('edit_seller')) && is_array($request->input('edit_seller'))) {
                $editSellerData = $request->input('edit_seller');
                $targetUserId = $productData['user_id'] ?? $product->user_id;
                if ($targetUserId) {
                    $sellerUser = User::find($targetUserId);
                    if ($sellerUser) {
                        $updateFields = array_filter([
                            'name' => $editSellerData['name'] ?? null,
                            'phone_number' => $editSellerData['phone_number'] ?? null,
                            'email' => $editSellerData['email'] ?? null,
                            'store_name' => $editSellerData['store_name'] ?? null,
                        ], fn ($v) => ! is_null($v) && $v !== '');

                        if (! empty($updateFields)) {
                            $sellerUser->update($updateFields);
                        }

                        if (! empty($editSellerData['address'])) {
                            $addr = $sellerUser->customerAddresses()->where('is_primary', true)->first()
                                ?? $sellerUser->customerAddresses()->first();
                            if ($addr) {
                                $addr->update(['full_address' => $editSellerData['address']]);
                            } else {
                                $sellerUser->customerAddresses()->create([
                                    'label' => 'Utama',
                                    'receiver_name' => $sellerUser->name,
                                    'phone_number' => $sellerUser->phone_number ?? '',
                                    'full_address' => $editSellerData['address'],
                                    'is_primary' => true,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $rawSku = trim($validated['sku'] ?? '');
        if (empty($rawSku)) {
            $rawSku = Str::upper(Str::slug($validated['name']));
        }
        $sku = $rawSku;
        $count = 1;
        while (Product::where('sku', $sku)->where('id', '!=', $product->id)->exists()) {
            $sku = $rawSku.'-'.$count;
            $count++;
        }
        $productData['sku'] = $sku;

        $product->update($productData);

        // Sync many-to-many relationships
        $product->categories()->sync($categoryIds);
        $product->brands()->sync($brandIds);

        // Sync Tier Prices for Master Product
        $product->tierPrices()->delete();
        if (! empty($validated['tier_prices'])) {
            foreach ($validated['tier_prices'] as $tp) {
                $product->tierPrices()->create([
                    'min_qty' => $tp['min_qty'],
                    'price' => $tp['price'],
                ]);
            }
        }

        $product->productPrice()->updateOrCreate(
            ['product_variant_id' => null],
            [
                'price' => $validated['price'],
                'cost' => $validated['cost'] ?? null,
            ]
        );

        $product->productStock()->updateOrCreate(
            ['product_variant_id' => null],
            [
                'stock' => $validated['stock'],
                'min_stock' => $validated['min_stock'] ?? 0,
                'min_purchase' => $validated['min_purchase'] ?? 1,
                'is_unlimited' => $validated['is_unlimited'] ?? false,
            ]
        );

        // Process photos
        $submittedPhotos = $request->input('photos', []);
        $keptImagePaths = [];

        foreach ($submittedPhotos as $photo) {
            if (! preg_match('/^data:image\/(\w+);base64,/', $photo)) {
                $keptImagePaths[] = ltrim($photo, '/');
            }
        }

        // Delete old photos that are not kept
        $existingImages = $product->images;
        foreach ($existingImages as $existingImage) {
            if (! in_array($existingImage->path, $keptImagePaths, true)) {
                $relativeDiskPath = $existingImage->path;
                if (str_starts_with($relativeDiskPath, 'storage/')) {
                    $relativeDiskPath = substr($relativeDiskPath, 8);
                }
                Storage::disk('public')->delete($relativeDiskPath);
                $existingImage->delete();
            }
        }

        // Save new photos and keep track of all final paths in order
        $finalImagePaths = [];
        foreach ($submittedPhotos as $photo) {
            if (preg_match('/^data:image\/(\w+);base64,/', $photo, $type)) {
                $photoBase64 = substr($photo, strpos($photo, ',') + 1);
                $type = strtolower($type[1]);
                $photoBase64 = base64_decode(str_replace(' ', '+', $photoBase64));
                $photoBase64 = ImageHelper::compress($photoBase64, $type, 75);
                $filename = 'product_'.$product->id.'_'.time().'_'.uniqid().'.'.$type;
                Storage::disk('public')->put('products/'.$filename, $photoBase64);
                $path = 'storage/products/'.$filename;

                $product->images()->create([
                    'path' => $path,
                    'is_main' => false,
                ]);
                $finalImagePaths[] = $path;
            } else {
                $finalImagePaths[] = ltrim($photo, '/');
            }
        }

        // Update is_main status based on the final order
        if (! empty($finalImagePaths)) {
            $mainPath = $finalImagePaths[0];
            foreach ($finalImagePaths as $index => $path) {
                $product->images()->where('path', $path)->update([
                    'is_main' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
            $product->update(['image' => $mainPath]);
        } else {
            $product->update(['image' => null]);
        }

        // Process variations inputs
        $variationsInput = $request->input('variations', []);
        $variantsInput = $request->input('variants', []);

        // Collect new variation image paths to avoid deleting active files
        $newVariationImages = [];
        foreach ($variationsInput as $vData) {
            if (! empty($vData['options'])) {
                foreach ($vData['options'] as $optData) {
                    if (! empty($optData['image']) && ! preg_match('/^data:image\/(\w+);base64,/', $optData['image'])) {
                        $newVariationImages[] = ltrim($optData['image'], '/');
                    }
                }
            }
        }

        $keptVariationIds = [];
        $keptOptionIds = [];
        $optionIdMap = []; // Maps frontend option IDs to DB option IDs

        foreach ($variationsInput as $vIndex => $vData) {
            // Find existing variation or create a new one
            $variation = null;
            if (! empty($vData['id']) && Str::isUuid($vData['id'])) {
                $variation = $product->variations()->find($vData['id']);
            }

            if ($variation) {
                $variation->update([
                    'name' => $vData['name'],
                    'sort_order' => $vIndex,
                ]);
            } else {
                $variation = $product->variations()->create([
                    'name' => $vData['name'],
                    'sort_order' => $vIndex,
                ]);
            }
            $keptVariationIds[] = $variation->id;

            // Process options of this variation
            if (! empty($vData['options'])) {
                foreach ($vData['options'] as $oIndex => $optData) {
                    $option = null;
                    if (! empty($optData['id']) && Str::isUuid($optData['id'])) {
                        $option = $variation->options()->find($optData['id']);
                    }

                    // Process base64 image
                    $imagePath = $optData['image'] ?? null;
                    if (! empty($optData['image']) && preg_match('/^data:image\/(\w+);base64,/', $optData['image'], $type)) {
                        $imgBase64 = substr($optData['image'], strpos($optData['image'], ',') + 1);
                        $type = strtolower($type[1]);
                        $imgBase64 = base64_decode(str_replace(' ', '+', $imgBase64));
                        $imgBase64 = ImageHelper::compress($imgBase64, $type, 75);
                        $filename = 'opt_'.$product->id.'_'.time().'_'.uniqid().'.'.$type;
                        Storage::disk('public')->put('products/'.$filename, $imgBase64);
                        $imagePath = 'storage/products/'.$filename;
                    }

                    if ($imagePath) {
                        $imagePath = ltrim($imagePath, '/');
                    }

                    if ($option) {
                        $option->update([
                            'name' => $optData['name'],
                            'description' => $optData['description'] ?? null,
                            'image' => $imagePath,
                            'sort_order' => $oIndex,
                        ]);
                    } else {
                        $option = $variation->options()->create([
                            'name' => $optData['name'],
                            'description' => $optData['description'] ?? null,
                            'image' => $imagePath,
                            'sort_order' => $oIndex,
                        ]);
                    }
                    $keptOptionIds[] = $option->id;
                    if (isset($optData['id'])) {
                        $optionIdMap[$optData['id']] = $option->id;
                    }
                }
            }
        }

        // Clean up deleted options
        $product->variations()->each(function ($variation) use ($keptOptionIds, $newVariationImages) {
            $variation->options()->whereNotIn('id', $keptOptionIds)->each(function ($option) use ($newVariationImages) {
                if ($option->image) {
                    $path = ltrim($option->image, '/');
                    if (! in_array($path, $newVariationImages, true)) {
                        $relativeDiskPath = $path;
                        if (str_starts_with($relativeDiskPath, 'storage/')) {
                            $relativeDiskPath = substr($relativeDiskPath, 8);
                        }
                        Storage::disk('public')->delete($relativeDiskPath);
                    }
                }
                $option->delete();
            });
        });

        // Clean up deleted variations
        $product->variations()->whereNotIn('id', $keptVariationIds)->delete();

        // Process variants combinations
        $keptVariantIds = [];

        if (! empty($variantsInput)) {
            foreach ($variantsInput as $vCombData) {
                // 1. Resolve frontend option IDs to DB option IDs
                $frontIds = explode('_', $vCombData['id']);
                $dbOptionIds = [];
                foreach ($frontIds as $fid) {
                    if (isset($optionIdMap[$fid])) {
                        $dbOptionIds[] = $optionIdMap[$fid];
                    } elseif (Str::isUuid($fid)) {
                        $dbOptionIds[] = $fid;
                    }
                }
                sort($dbOptionIds);

                // 2. Look for an existing variant with the exact same option combination
                $existingVariants = $product->variants()->with('options')->get();
                $matchedVariant = null;
                foreach ($existingVariants as $ev) {
                    $evOptIds = $ev->options->pluck('id')->toArray();
                    sort($evOptIds);
                    if ($evOptIds === $dbOptionIds) {
                        $matchedVariant = $ev;
                        break;
                    }
                }

                // 3. Resolve variant image
                $variantImage = null;
                foreach ($dbOptionIds as $dbOptId) {
                    $dbOption = ProductVariationOption::find($dbOptId);
                    if ($dbOption && $dbOption->image) {
                        $variantImage = $dbOption->image;
                        break;
                    }
                }

                $hasCustomWeight = ! empty($vCombData['is_custom']) && ! empty($vCombData['custom_weight']);
                $variantData = [
                    'sku' => $vCombData['sku'],
                    'weight' => $hasCustomWeight ? ($vCombData['weight'] ?: null) : null,
                    'length' => $hasCustomWeight ? ($vCombData['length'] ?: null) : null,
                    'width' => $hasCustomWeight ? ($vCombData['width'] ?: null) : null,
                    'height' => $hasCustomWeight ? ($vCombData['height'] ?: null) : null,
                    'image' => $variantImage,
                ];

                if ($matchedVariant) {
                    $newSku = trim($variantData['sku'] ?? '');
                    if (! empty($newSku)) {
                        $variantSku = $newSku;
                        $vCount = 1;
                        while (ProductVariant::where('sku', $variantSku)->where('id', '!=', $matchedVariant->id)->exists()) {
                            $variantSku = $newSku.'-'.$vCount;
                            $vCount++;
                        }
                        $variantData['sku'] = $variantSku;
                    }

                    // Update existing
                    $matchedVariant->update($variantData);
                    $variant = $matchedVariant;
                } else {
                    $rawVariantSku = trim($variantData['sku'] ?? '');
                    if (empty($rawVariantSku)) {
                        $rawVariantSku = $product->sku.'-VAR-'.Str::random(4);
                    }
                    $variantSku = $rawVariantSku;
                    $vCount = 1;
                    while (ProductVariant::where('sku', $variantSku)->exists()) {
                        $variantSku = $rawVariantSku.'-'.$vCount;
                        $vCount++;
                    }
                    $variantData['sku'] = $variantSku;

                    // Create new
                    $variant = $product->variants()->create($variantData);
                    $variant->options()->attach($dbOptionIds);
                }
                $keptVariantIds[] = $variant->id;

                // 4. Custom Variant Price
                if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_price'])) {
                    $variant->productPrice()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'price' => $vCombData['price'] ?: 0,
                            'cost' => $vCombData['cost'] ?: null,
                        ]
                    );

                    // Sync variant tier prices
                    $variant->tierPrices()->delete();
                    if (! empty($vCombData['tier_prices'])) {
                        foreach ($vCombData['tier_prices'] as $tp) {
                            $variant->tierPrices()->create([
                                'product_id' => $product->id,
                                'min_qty' => $tp['min_qty'],
                                'price' => $tp['price'],
                            ]);
                        }
                    }
                } else {
                    $variant->productPrice()->delete();
                    $variant->tierPrices()->delete();
                }

                // 5. Custom Variant Stock
                if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_stock'])) {
                    $variant->productStock()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'stock' => $vCombData['stock'] ?: 0,
                            'min_stock' => $vCombData['min_stock'] ?: 0,
                            'min_purchase' => $vCombData['min_purchase'] ?: 1,
                            'is_unlimited' => ! empty($vCombData['is_unlimited']),
                        ]
                    );
                } else {
                    $variant->productStock()->delete();
                }
            }
        }

        // Clean up deleted variants
        $product->variants()->whereNotIn('id', $keptVariantIds)->each(function ($variant) {
            $variant->productPrice()->delete();
            $variant->productStock()->delete();
            $variant->options()->detach();
            $variant->delete();
        });

        return redirect()->route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $product->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:products,id',
        ]);

        $ids = $request->input('ids');
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $ids = Product::whereIn('id', $ids)->where('user_id', $user->id)->pluck('id')->all();
        }

        \DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $product = Product::find($id);
                if ($product) {
                    $product->delete();
                }
            }
        });

        return redirect()->back()->with('success', 'Produk terpilih berhasil dihapus.');
    }

    public function toggleActive(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $product->update(['active' => ! $product->active]);

        return redirect()->back()->with('success', 'Product status updated.');
    }

    public function managePrices(Request $request)
    {
        $search = $request->input('search');
        $user = $request->user();

        $query = Product::with([
            'category',
            'productPrice',
            'tierPrices',
            'variants.options',
            'variants.productPrice',
            'variants.tierPrices',
        ])->latest();

        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->where('user_id', $user->id);
        }

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $products = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Store/Prices', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function updatePrices(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.cost' => 'nullable|numeric|min:0',
            'products.*.tax_enabled' => 'boolean',
            'products.*.tier_prices' => 'nullable|array',
            'products.*.tier_prices.*.min_qty' => 'required|integer|min:2',
            'products.*.tier_prices.*.price' => 'required|numeric|min:0',
            'products.*.variants' => 'nullable|array',
            'products.*.variants.*.id' => 'required|exists:product_variants,id',
            'products.*.variants.*.price' => 'nullable|numeric|min:0',
            'products.*.variants.*.cost' => 'nullable|numeric|min:0',
            'products.*.variants.*.tier_prices' => 'nullable|array',
            'products.*.variants.*.tier_prices.*.min_qty' => 'required|integer|min:2',
            'products.*.variants.*.tier_prices.*.price' => 'required|numeric|min:0',
        ]);

        $user = $request->user();
        \DB::transaction(function () use ($request, $user) {
            foreach ($request->input('products') as $pData) {
                $pQuery = Product::query();
                if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
                    $pQuery->where('user_id', $user->id);
                }
                $product = $pQuery->findOrFail($pData['id']);
                $product->update([
                    'tax_enabled' => ! empty($pData['tax_enabled']),
                ]);

                $product->productPrice()->updateOrCreate(
                    ['product_variant_id' => null],
                    [
                        'price' => $pData['price'],
                        'cost' => $pData['cost'] ?: null,
                    ]
                );

                // Sync main product tier prices
                $product->tierPrices()->delete();
                if (! empty($pData['tier_prices'])) {
                    foreach ($pData['tier_prices'] as $tp) {
                        $product->tierPrices()->create([
                            'min_qty' => $tp['min_qty'],
                            'price' => $tp['price'],
                        ]);
                    }
                }

                if (! empty($pData['variants'])) {
                    foreach ($pData['variants'] as $vData) {
                        $variant = $product->variants()->findOrFail($vData['id']);

                        $price = isset($vData['price']) && $vData['price'] !== '' ? floatval($vData['price']) : 0;
                        if ($price > 0) {
                            $variant->productPrice()->updateOrCreate(
                                ['product_id' => $product->id],
                                [
                                    'price' => $price,
                                    'cost' => $vData['cost'] ?: null,
                                ]
                            );

                            // Sync variant tier prices
                            $variant->tierPrices()->delete();
                            if (! empty($vData['tier_prices'])) {
                                foreach ($vData['tier_prices'] as $tp) {
                                    $variant->tierPrices()->create([
                                        'product_id' => $product->id,
                                        'min_qty' => $tp['min_qty'],
                                        'price' => $tp['price'],
                                    ]);
                                }
                            }
                        } else {
                            $variant->productPrice()->delete();
                            $variant->tierPrices()->delete();
                        }
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Harga berhasil diperbarui.');
    }

    public function manageStocks(Request $request)
    {
        $search = $request->input('search');
        $user = $request->user();

        $query = Product::with([
            'category',
            'productStock',
            'variants.options',
            'variants.productStock',
        ])->latest();

        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->where('user_id', $user->id);
        }

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $products = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Store/Stocks', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function updateStocks(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.stock' => 'nullable|integer|min:0',
            'products.*.min_stock' => 'nullable|integer|min:0',
            'products.*.min_purchase' => 'nullable|integer|min:1',
            'products.*.is_unlimited' => 'boolean',
            'products.*.variants' => 'nullable|array',
        ]);

        $user = $request->user();
        \DB::transaction(function () use ($request, $user) {
            foreach ($request->input('products') as $pData) {
                $pQuery = Product::query();
                if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
                    $pQuery->where('user_id', $user->id);
                }
                $product = $pQuery->findOrFail($pData['id']);

                $product->productStock()->updateOrCreate(
                    ['product_variant_id' => null],
                    [
                        'stock' => $pData['stock'] ?: 0,
                        'min_stock' => $pData['min_stock'] ?: 0,
                        'min_purchase' => $pData['min_purchase'] ?: 1,
                        'is_unlimited' => ! empty($pData['is_unlimited']),
                    ]
                );

                if (! empty($pData['variants'])) {
                    foreach ($pData['variants'] as $vData) {
                        $variant = $product->variants()->findOrFail($vData['id']);

                        $hasStock = isset($vData['stock']) && $vData['stock'] !== '';
                        if ($hasStock) {
                            $variant->productStock()->updateOrCreate(
                                ['product_id' => $product->id],
                                [
                                    'stock' => intval($vData['stock']),
                                    'min_stock' => isset($vData['min_stock']) && $vData['min_stock'] !== '' ? intval($vData['min_stock']) : 0,
                                    'min_purchase' => isset($vData['min_purchase']) && $vData['min_purchase'] !== '' ? intval($vData['min_purchase']) : 1,
                                    'is_unlimited' => ! empty($vData['is_unlimited']),
                                ]
                            );
                        } else {
                            $variant->productStock()->delete();
                        }
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Stok berhasil diperbarui.');
    }

    public function manageShipping(Request $request)
    {
        $search = $request->input('search');
        $user = $request->user();

        $query = Product::with([
            'category',
            'variants.options',
        ])->latest();

        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $query->where('user_id', $user->id);
        }

        if ($search) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $products = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/Store/Shipping', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function updateShipping(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.weight' => 'nullable|integer|min:0',
            'products.*.length' => 'nullable|integer|min:0',
            'products.*.width' => 'nullable|integer|min:0',
            'products.*.height' => 'nullable|integer|min:0',
            'products.*.variants' => 'nullable|array',
        ]);

        $user = $request->user();
        \DB::transaction(function () use ($request, $user) {
            foreach ($request->input('products') as $pData) {
                $pQuery = Product::query();
                if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
                    $pQuery->where('user_id', $user->id);
                }
                $product = $pQuery->findOrFail($pData['id']);
                $product->update([
                    'weight' => $pData['weight'] ?: 0,
                    'length' => $pData['length'] ?: 0,
                    'width' => $pData['width'] ?: 0,
                    'height' => $pData['height'] ?: 0,
                ]);

                if (! empty($pData['variants'])) {
                    foreach ($pData['variants'] as $vData) {
                        $variant = $product->variants()->findOrFail($vData['id']);

                        $hasWeight = isset($vData['weight']) && $vData['weight'] !== '';
                        $hasLength = isset($vData['length']) && $vData['length'] !== '';
                        $hasWidth = isset($vData['width']) && $vData['width'] !== '';
                        $hasHeight = isset($vData['height']) && $vData['height'] !== '';

                        if ($hasWeight || $hasLength || $hasWidth || $hasHeight) {
                            $variant->update([
                                'weight' => $hasWeight ? intval($vData['weight']) : null,
                                'length' => $hasLength ? intval($vData['length']) : null,
                                'width' => $hasWidth ? intval($vData['width']) : null,
                                'height' => $hasHeight ? intval($vData['height']) : null,
                            ]);
                        } else {
                            $variant->update([
                                'weight' => null,
                                'length' => null,
                                'width' => null,
                                'height' => null,
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Dimensi & Berat berhasil diperbarui.');
    }

    public function downloadImportTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_import_produk.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Nama Produk',
            'SKU',
            'Kategori',
            'Brand',
            'Ringkasan Singkat',
            'Deskripsi',
            'Kondisi (Baru/Bekas)',
            'Apakah Digital',
            'Harga Jual',
            'Harga Modal',
            'Stok',
            'Batas Minimum',
            'Min Pembelian',
            'Apakah Unlimited Stock',
            'Berat (gram)',
            'Panjang (cm)',
            'Lebar (cm)',
            'Tinggi (cm)',
            'Spesifikasi',
            'Variasi 1 Nama',
            'Variasi 1 Nilai',
            'Variasi 2 Nama',
            'Variasi 2 Nilai',
            'Harga Varian',
            'Stok Varian',
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write the Excel separator instruction
            fwrite($file, "sep=,\n");

            fputcsv($file, $columns);

            // Add some sample rows
            fputcsv($file, [
                'Kaos Combed 30s',
                'COM-30S-001',
                'Pakaian Pria, Kaos',
                'KaosKu',
                'Kaos combed kualitas premium super adem.',
                'Bahan Combed 30s premium.',
                '0',
                '100000',
                '70000',
                '100',
                '5',
                '1',
                '0',
                '200',
                '30',
                '25',
                '2',
                'Bahan: Cotton Combed 30s; Gaya: Kasual',
                'Warna',
                'Merah',
                'Ukuran',
                'L',
                '100000',
                '50',
            ]);
            fputcsv($file, [
                'Kaos Combed 30s',
                'COM-30S-001',
                'Pakaian Pria, Kaos',
                'KaosKu',
                'Kaos combed kualitas premium super adem.',
                'Bahan Combed 30s premium.',
                '0',
                '100000',
                '70000',
                '100',
                '5',
                '1',
                '0',
                '200',
                '30',
                '25',
                '2',
                'Bahan: Cotton Combed 30s; Gaya: Kasual',
                'Warna',
                'Merah',
                'Ukuran',
                'XL',
                '105000',
                '30',
            ]);
            fputcsv($file, [
                'Kaos Combed 30s',
                'COM-30S-001',
                'Pakaian Pria, Kaos',
                'KaosKu',
                'Kaos combed kualitas premium super adem.',
                'Bahan Combed 30s premium.',
                '0',
                '100000',
                '70000',
                '100',
                '5',
                '1',
                '0',
                '200',
                '30',
                '25',
                '2',
                'Bahan: Cotton Combed 30s; Gaya: Kasual',
                'Warna',
                'Hitam',
                'Ukuran',
                'L',
                '100000',
                '20',
            ]);
            fputcsv($file, [
                'Sepeda Gunung',
                'BIKE-MTB-001',
                'Sepeda, Olahraga',
                'Polygon',
                'Sepeda gunung Polygon tangguh dan kuat.',
                'Sepeda gunung Polygon dual suspension.',
                '0',
                '3500000',
                '2500000',
                '10',
                '2',
                '1',
                '0',
                '15000',
                '140',
                '20',
                '80',
                'Frame: AluxX; Fork: SR Suntour',
                '',
                '',
                '',
                '',
                '',
                '',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportProducts(Request $request)
    {
        $user = $request->user();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="export_produk_'.date('Y-m-d_H-i-s').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Nama Produk',
            'SKU',
            'Kategori',
            'Brand',
            'Ringkasan Singkat',
            'Deskripsi',
            'Apakah Digital',
            'Harga Jual',
            'Harga Modal',
            'Stok',
            'Batas Minimum',
            'Min Pembelian',
            'Apakah Unlimited Stock',
            'Berat (gram)',
            'Panjang (cm)',
            'Lebar (cm)',
            'Tinggi (cm)',
            'Spesifikasi',
            'Variasi 1 Nama',
            'Variasi 1 Nilai',
            'Variasi 2 Nama',
            'Variasi 2 Nilai',
            'Harga Varian',
            'Stok Varian',
        ];

        $callback = function () use ($columns, $user) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write the Excel separator instruction
            fwrite($file, "sep=,\n");

            fputcsv($file, $columns);

            $query = Product::with([
                'categories',
                'brandRelation',
                'productPrice',
                'productStock',
                'variations.options',
                'variants.options',
                'variants.productPrice',
                'variants.productStock',
            ])->latest();

            if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
                $query->where('user_id', $user->id);
            }

            $query->chunk(100, function ($products) use ($file) {
                foreach ($products as $product) {
                    $categories = implode(', ', $product->categories->pluck('name')->toArray());

                    $specs = [];
                    if (is_array($product->specifications)) {
                        foreach ($product->specifications as $spec) {
                            if (isset($spec['name']) && isset($spec['value'])) {
                                $specs[] = "{$spec['name']}: {$spec['value']}";
                            }
                        }
                    }
                    $specString = implode('; ', $specs);

                    if ($product->variants && $product->variants->count() > 0) {
                        $variations = $product->variations;
                        $var1 = $variations->get(0);
                        $var2 = $variations->get(1);

                        $var1Name = $var1?->name ?? '';
                        $var2Name = $var2?->name ?? '';

                        foreach ($product->variants as $variant) {
                            $var1Val = '';
                            $var2Val = '';

                            foreach ($variant->options as $option) {
                                if ($var1 && $option->product_variation_id === $var1->id) {
                                    $var1Val = $option->name;
                                } elseif ($var2 && $option->product_variation_id === $var2->id) {
                                    $var2Val = $option->name;
                                }
                            }

                            fputcsv($file, [
                                $product->name,
                                $product->sku,
                                $categories,
                                $product->brand,
                                $product->summary,
                                $product->description,
                                $product->is_digital ? '1' : '0',
                                $product->productPrice?->price,
                                $product->productPrice?->cost,
                                $product->productStock?->stock,
                                $product->productStock?->min_stock,
                                $product->productStock?->min_purchase,
                                $product->productStock?->is_unlimited ? '1' : '0',
                                $product->weight,
                                $product->length,
                                $product->width,
                                $product->height,
                                $specString,
                                $var1Name,
                                $var1Val,
                                $var2Name,
                                $var2Val,
                                $variant->productPrice?->price,
                                $variant->productStock?->stock,
                            ]);
                        }
                    } else {
                        fputcsv($file, [
                            $product->name,
                            $product->sku,
                            $categories,
                            $product->brand,
                            $product->summary,
                            $product->description,
                            $product->is_digital ? '1' : '0',
                            $product->productPrice?->price,
                            $product->productPrice?->cost,
                            $product->productStock?->stock,
                            $product->productStock?->min_stock,
                            $product->productStock?->min_purchase,
                            $product->productStock?->is_unlimited ? '1' : '0',
                            $product->weight,
                            $product->length,
                            $product->width,
                            $product->height,
                            $specString,
                            '',
                            '',
                            '',
                            '',
                            '',
                            '',
                        ]);
                    }
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'required|string|max:100',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.cost' => 'nullable|numeric|min:0',
            'products.*.stock' => 'nullable|integer|min:0',
            'products.*.min_stock' => 'nullable|integer|min:0',
            'products.*.min_purchase' => 'nullable|integer|min:1',
            'products.*.weight' => 'nullable|integer|min:0',
            'products.*.length' => 'nullable|integer|min:0',
            'products.*.width' => 'nullable|integer|min:0',
            'products.*.height' => 'nullable|integer|min:0',
            'products.*.description' => 'required|string',
            'products.*.summary' => 'nullable|string|max:255',
            'products.*.tax_enabled' => 'boolean',
            'products.*.is_digital' => 'boolean',
            'products.*.is_unlimited' => 'boolean',
            'products.*.specifications' => 'nullable|array',
            'products.*.category_names' => 'nullable|string',
            'products.*.brand_name' => 'nullable|string',
            'products.*.variations' => 'nullable|array',
            'products.*.variants' => 'nullable|array',
            'auto_fetch_images' => 'nullable|boolean',
        ]);

        $autoFetch = $request->boolean('auto_fetch_images', (bool) config('services.products.import_auto_fetch_images', true));
        $userId = $request->user()?->id;
        $productsInput = $request->input('products');
        $count = count($productsInput);

        try {
            if ($count <= 20) {
                ImportProductsJob::dispatchSync($productsInput, $autoFetch, $userId);
                $successMessage = "Berhasil mengimpor {$count} produk.";
            } else {
                ImportProductsJob::dispatch($productsInput, $autoFetch, $userId);
                $successMessage = "Import {$count} produk telah dijadwalkan di latar belakang (Supervisor). Proses ini akan mengimpor data produk secara bertahap.";
            }
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Gagal mengimpor produk: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['error' => 'Gagal mengimpor produk: '.$e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $successMessage,
            ]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * Validate base64 images to ensure they are under 2MB.
     */
    private function validateBase64Images(Request $request): void
    {
        if ($request->has('photos')) {
            foreach ($request->input('photos') as $photo) {
                if (preg_match('/^data:image\/(\w+);base64,/', $photo)) {
                    $base64Data = substr($photo, strpos($photo, ',') + 1);
                    $decodedSize = strlen(base64_decode($base64Data));
                    if ($decodedSize > 10 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            'photos' => ['Ukuran gambar produk maksimal 10MB.'],
                        ]);
                    }
                }
            }
        }

        if ($request->has('variations')) {
            foreach ($request->input('variations') as $vData) {
                if (! empty($vData['options'])) {
                    foreach ($vData['options'] as $optData) {
                        if (! empty($optData['image']) && preg_match('/^data:image\/(\w+);base64,/', $optData['image'])) {
                            $base64Data = substr($optData['image'], strpos($optData['image'], ',') + 1);
                            $decodedSize = strlen(base64_decode($base64Data));
                            if ($decodedSize > 10 * 1024 * 1024) {
                                throw ValidationException::withMessages([
                                    'variations' => ['Ukuran gambar opsi varian maksimal 10MB.'],
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Reorder products.
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.order' => ['required', 'integer'],
        ]);

        foreach ($request->products as $productData) {
            Product::where('id', $productData['id'])->update([
                'order' => $productData['order'],
            ]);
        }

        return back()->with('success', 'Urutan produk berhasil diperbarui.');
    }

    /**
     * Find existing seller by phone/email or create a new seller user safely without collisions.
     */
    private function findOrCreateSeller(array $newSellerData): User
    {
        $existingUser = null;
        $phone = trim($newSellerData['phone_number'] ?? '');
        $email = trim($newSellerData['email'] ?? '');
        $name = trim($newSellerData['name'] ?? 'Seller');

        if (! empty($phone)) {
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $existingUser = User::where('phone_number', $phone)
                ->when($cleanPhone, fn ($q) => $q->orWhere('phone_number', $cleanPhone))
                ->first();
        }

        if (! $existingUser && ! empty($email)) {
            $existingUser = User::where('email', $email)->first();
        }

        if ($existingUser) {
            $storeSlug = $existingUser->store_slug;
            if (empty($storeSlug)) {
                $rawSlug = Str::slug($name);
                $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
                while (User::where('store_slug', $storeSlug)->where('id', '!=', $existingUser->id)->exists()) {
                    $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
                }
            }

            $existingUser->update([
                'is_seller' => true,
                'is_active' => true,
                'store_name' => $existingUser->store_name ?: $name,
                'store_slug' => $storeSlug,
            ]);

            $newUser = $existingUser;
        } else {
            $sellerEmail = ! empty($email)
                ? $email
                : ('seller_'.time().'_'.Str::random(4).'@bizmate.local');

            $rawSlug = Str::slug($name);
            $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
            while (User::where('store_slug', $storeSlug)->exists()) {
                $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
            }

            $newUser = User::create([
                'name' => $name,
                'email' => $sellerEmail,
                'password' => bcrypt('password'),
                'phone_number' => $phone ?: null,
                'is_seller' => true,
                'is_active' => true,
                'store_name' => $name,
                'store_slug' => $storeSlug,
            ]);
        }

        if (! empty($newSellerData['address'])) {
            $addr = $newUser->customerAddresses()->where('is_primary', true)->first()
                ?? $newUser->customerAddresses()->first();

            if ($addr) {
                $addr->update(['full_address' => $newSellerData['address']]);
            } else {
                $newUser->customerAddresses()->create([
                    'label' => 'Utama',
                    'receiver_name' => $newUser->name,
                    'phone_number' => $newUser->phone_number ?? '',
                    'full_address' => $newSellerData['address'],
                    'is_primary' => true,
                ]);
            }
        }

        return $newUser;
    }

    /**
     * Authorize seller access to product.
     */
    private function authorizeSellerProduct(Request $request, Product $product): void
    {
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            if ($product->user_id !== $user->id) {
                abort(403, 'Anda tidak memiliki akses untuk mengelola produk ini.');
            }
        }
    }

    /**
     * Renew product listing duration.
     */
    public function renewListing(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $isSellerMode = (bool) config('app.is_seller', false);
        if (! $isSellerMode) {
            $product->update([
                'listing_expires_at' => null,
                'listing_fee' => 0,
                'listing_days' => 0,
                'active' => true,
            ]);

            return back()->with('success', 'Produk tidak terbatas (unlimited).');
        }

        $config = $this->getListingPricingConfig();
        $calc = $this->calculateListingFeeAndDays($request, $config);

        $days = $calc['days'];
        $fee = (int) round($calc['fee']);
        $user = $request->user();

        $serverKey = Setting::where('key', 'midtrans_server_key')->value('value');
        $orderId = 'LISTING-'.substr(md5(uniqid((string) mt_rand(), true)), 0, 8);

        $qrImage = null;
        $qrString = null;

        if (! empty($serverKey) && $fee > 0) {
            try {
                $chargeResult = MidtransService::charge(
                    $orderId,
                    $fee,
                    'qris',
                    [
                        'name' => $user->name ?? 'Seller',
                        'email' => $user->email ?? 'seller@example.com',
                        'phone' => $user->phone ?? '',
                    ]
                );

                if ($chargeResult['success'] && ! empty($chargeResult['data'])) {
                    $qrImage = $chargeResult['data']['qr_image'] ?? null;
                    $qrString = $chargeResult['data']['qr_string'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::warning('Midtrans charge failed: '.$e->getMessage());
            }
        }

        if (! $qrImage && ! $qrString) {
            $dummyPayload = '00020101021226680016ID.CO.QRIS.WWW01189360091400000000000215'.str_pad((string) $fee, 12, '0', STR_PAD_LEFT).'5802ID5910BIZMATE006007JAKARTA6304ABCD';
            $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($dummyPayload);
            $qrString = $dummyPayload;
        }

        return back()->with('qris_payment', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'fee' => $fee,
            'days' => $days,
            'qr_image' => $qrImage,
            'qr_string' => $qrString,
            'expiry_time' => now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /**
     * Get QRIS payment payload directly for modal.
     * Saves a pending record so the Midtrans webhook can auto-confirm it.
     */
    public function getQrisListing(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $isSellerMode = (bool) config('app.is_seller', false);
        if (! $isSellerMode) {
            return response()->json(['success' => false, 'message' => 'Mode Penjual tidak aktif. Seluruh produk berstatus Unlimited.'], 400);
        }

        $config = $this->getListingPricingConfig();
        $calc = $this->calculateListingFeeAndDays($request, $config);

        $days = $calc['days'];
        $fee = (int) round($calc['fee']);
        $user = $request->user();

        $serverKey = Setting::where('key', 'midtrans_server_key')->value('value');
        $orderId = 'LISTING-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 12));

        $qrImage = null;
        $qrString = null;
        $isSandbox = false;

        if (! empty($serverKey) && $fee > 0) {
            try {
                $chargeResult = MidtransService::charge(
                    $orderId,
                    $fee,
                    'qris',
                    [
                        'name' => $user->name ?? 'Seller',
                        'email' => $user->email ?? 'seller@example.com',
                        'phone' => $user->phone ?? '',
                    ]
                );

                if ($chargeResult['success'] && ! empty($chargeResult['data'])) {
                    $qrImage = $chargeResult['data']['qr_image'] ?? null;
                    $qrString = $chargeResult['data']['qr_string'] ?? null;
                    $isSandbox = str_contains(MidtransService::getCoreApiBaseUrl(), 'sandbox');
                }
            } catch (\Throwable $e) {
                Log::warning('Midtrans charge failed: '.$e->getMessage());
            }
        }

        if (! $qrImage && ! $qrString) {
            $dummyPayload = '00020101021226680016ID.CO.QRIS.WWW01189360091400000000000215'.str_pad((string) $fee, 12, '0', STR_PAD_LEFT).'5802ID5910BIZMATE006007JAKARTA6304ABCD';
            $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($dummyPayload);
            $qrString = $dummyPayload;
            $isSandbox = true;
        }

        // Save a pending record immediately so the Midtrans webhook can auto-confirm it.
        ProductListingPayment::updateOrCreate(
            ['order_id' => $orderId],
            [
                'product_id' => $product->id,
                'user_id' => $product->user_id ?: $user->id,
                'amount' => $fee,
                'days' => $days,
                'payment_method' => 'qris',
                'status' => 'pending',
                'paid_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'qris_payment' => [
                'order_id' => $orderId,
                'product_id' => $product->id,
                'fee' => $fee,
                'days' => $days,
                'qr_image' => $qrImage,
                'qr_string' => $qrString,
                'qr_image_url' => $qrImage,
                'is_sandbox' => $isSandbox,
                'expiry_time' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirm QRIS payment manually (fallback if webhook didn't fire).
     * Idempotent: if webhook already confirmed payment, extend product only.
     */
    public function confirmQrisListing(Request $request, Product $product)
    {
        $this->authorizeSellerProduct($request, $product);

        $days = (int) $request->input('days', 15);
        $fee = (float) $request->input('fee', 0);
        $orderId = $request->input('order_id') ?: ('LISTING-'.strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 12)));
        $user = $request->user();

        // Check if webhook already paid this order
        $existingPayment = ProductListingPayment::where('order_id', $orderId)->first();

        if ($existingPayment && $existingPayment->status === 'paid') {
            // Webhook already handled it; just make sure product is active
            if (! ($product->listing_expires_at && $product->listing_expires_at->isFuture())) {
                $product->update(['active' => true]);
            }

            return back()->with('success', "Pembayaran sudah dikonfirmasi otomatis! Masa aktif produk aktif {$existingPayment->days} hari.");
        }

        $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
            ? $product->listing_expires_at
            : now();

        $newExpiresAt = (clone $baseDate)->addDays($days);

        $product->update([
            'listing_expires_at' => $newExpiresAt,
            'listing_fee' => (float) $product->listing_fee + $fee,
            'listing_days' => (int) $product->listing_days + $days,
            'active' => true,
        ]);

        if ($existingPayment) {
            // Update the pending record to paid
            $existingPayment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            $paymentToBroadcast = $existingPayment;
        } else {
            $paymentToBroadcast = ProductListingPayment::create([
                'order_id' => $orderId,
                'product_id' => $product->id,
                'user_id' => $product->user_id ?: $user->id,
                'amount' => $fee,
                'days' => $days,
                'payment_method' => 'qris',
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        event(new ListingPaymentConfirmed($paymentToBroadcast));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Pembayaran QRIS berhasil! Masa aktif produk diperpanjang {$days} hari.",
                'status' => 'paid',
                'payment' => $paymentToBroadcast,
            ]);
        }

        return back()->with('success', "Pembayaran QRIS berhasil! Masa aktif produk diperpanjang {$days} hari.");
    }

    /**
     * Check the status of a listing payment by order_id (for frontend polling & Reverb).
     * If pending, proactively checks Midtrans Core API status for instant confirmation.
     */
    public function listingPaymentStatus(Request $request, string $orderId)
    {
        $user = $request->user();
        $isSuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();

        $payment = ProductListingPayment::where('order_id', $orderId)->first();

        if (! $payment) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if (! $isSuperAdmin && $payment->user_id !== $user->id) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        // If pending, proactively verify status directly with Midtrans API
        if ($payment->status === 'pending') {
            try {
                $serverKey = Setting::where('key', 'midtrans_server_key')->value('value')
                    ?: config('app.midtrans.server_key', '');

                if ($serverKey) {
                    $isProduction = (Setting::where('key', 'midtrans_is_production')->value('value') === 'true');
                    $baseUrl = $isProduction
                        ? 'https://api.midtrans.com/v2/'
                        : 'https://api.sandbox.midtrans.com/v2/';

                    $response = Http::withHeaders([
                        'Authorization' => 'Basic '.base64_encode($serverKey.':'),
                        'Accept' => 'application/json',
                    ])->timeout(4)->get($baseUrl.$payment->order_id.'/status');

                    if ($response->successful()) {
                        $data = $response->json();
                        $trxStatus = $data['transaction_status'] ?? null;
                        $fraudStatus = $data['fraud_status'] ?? null;

                        $isSuccess = ($trxStatus === 'settlement') ||
                                     ($trxStatus === 'capture' && $fraudStatus === 'accept');

                        if ($isSuccess) {
                            DB::transaction(function () use ($payment, $data) {
                                $product = Product::find($payment->product_id);
                                if ($product) {
                                    $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
                                        ? $product->listing_expires_at
                                        : now();

                                    $product->update([
                                        'listing_expires_at' => (clone $baseDate)->addDays($payment->days),
                                        'listing_fee' => (float) $product->listing_fee + (float) $payment->amount,
                                        'listing_days' => (int) $product->listing_days + $payment->days,
                                        'active' => true,
                                    ]);
                                }

                                $payment->update([
                                    'status' => 'paid',
                                    'paid_at' => now(),
                                    'gateway_transaction_id' => $data['transaction_id'] ?? null,
                                    'gateway_response' => $data,
                                ]);
                            });

                            // Broadcast Reverb Event immediately
                            event(new ListingPaymentConfirmed($payment->fresh()));
                        } elseif (in_array($trxStatus, ['cancel', 'deny', 'expire'])) {
                            $payment->update([
                                'status' => 'failed',
                                'gateway_response' => $data,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Midtrans Proactive Status Check Warning: '.$e->getMessage());
            }
        }

        return response()->json([
            'status' => $payment->status,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'days' => $payment->days,
            'amount' => (float) $payment->amount,
        ]);
    }

    /**
     * Cancel a pending listing payment.
     */
    public function cancelQrisListing(Request $request, string $orderId)
    {
        $user = $request->user();
        $isSuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();

        $payment = ProductListingPayment::where('order_id', $orderId)->first();

        if (! $payment) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan tidak ditemukan.'], 404);
            }

            return back()->with('error', 'Tagihan tidak ditemukan.');
        }

        if (! $isSuperAdmin && $payment->user_id !== $user->id) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            return back()->with('error', 'Akses ditolak.');
        }

        if ($payment->status === 'paid') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan sudah lunas dan tidak dapat dibatalkan.'], 422);
            }

            return back()->with('error', 'Tagihan sudah lunas dan tidak dapat dibatalkan.');
        }

        $payment->update([
            'status' => 'failed',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Tagihan {$orderId} berhasil dibatalkan.",
                'status' => 'failed',
            ]);
        }

        return back()->with('success', "Tagihan {$orderId} berhasil dibatalkan.");
    }

    /**
     * Admin/Super Admin override to adjust, reset, or set product listing expiration directly.
     */
    public function adminAdjustListing(Request $request, Product $product)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Admin') || ! $user->is_seller;

        if (! $isSuperAdmin) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Hanya Super Admin/Admin yang berhak mengelola masa aktif.'], 403);
            }

            abort(403, 'Hanya Super Admin/Admin yang berhak mengubah masa aktif listing produk secara langsung.');
        }

        $request->validate([
            'action' => 'required|in:add_days,set_days,set_date,set_unlimited,reset_expired',
            'days' => 'nullable|integer',
            'expires_at' => 'nullable|date',
        ]);

        $action = $request->input('action');

        switch ($action) {
            case 'add_days':
                $days = (int) $request->input('days', 0);
                $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
                    ? $product->listing_expires_at
                    : now();
                $newExpiresAt = (clone $baseDate)->addDays($days);
                $product->update([
                    'listing_expires_at' => $newExpiresAt,
                    'listing_days' => max(0, (int) $product->listing_days + $days),
                    'active' => true,
                ]);
                $msg = "Berhasil menambah masa aktif produk {$product->name} sebanyak +{$days} hari.";
                break;

            case 'set_days':
                $days = max(0, (int) $request->input('days', 0));
                $newExpiresAt = $days > 0 ? now()->addDays($days) : now()->subMinute();
                $product->update([
                    'listing_expires_at' => $newExpiresAt,
                    'listing_days' => $days,
                    'active' => $days > 0,
                ]);
                $msg = "Berhasil mengatur masa aktif produk {$product->name} menjadi {$days} hari dari sekarang.";
                break;

            case 'set_date':
                $dateStr = $request->input('expires_at');
                if (! $dateStr) {
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'Tanggal kedaluwarsa harus diisi.'], 422);
                    }

                    return back()->with('error', 'Tanggal kedaluwarsa harus diisi.');
                }
                $newExpiresAt = Carbon::parse($dateStr);
                $isActive = $newExpiresAt->isFuture();
                $product->update([
                    'listing_expires_at' => $newExpiresAt,
                    'active' => $isActive,
                ]);
                $msg = "Berhasil mengatur tanggal kedaluwarsa produk {$product->name} ke ".$newExpiresAt->format('d M Y H:i');
                break;

            case 'set_unlimited':
                $product->update([
                    'listing_expires_at' => null,
                    'active' => true,
                ]);
                $msg = "Masa aktif produk {$product->name} berhasil diubah menjadi Tanpa Batas (Unlimited).";
                break;

            case 'reset_expired':
                $product->update([
                    'listing_expires_at' => now()->subMinute(),
                    'active' => false,
                ]);
                $msg = "Masa aktif produk {$product->name} berhasil di-reset (Kedaluwarsa / Nonaktif).";
                break;

            default:
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Aksi tidak valid.'], 422);
                }

                return back()->with('error', 'Aksi tidak valid.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'product' => $product->fresh(),
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Get listing payments history list.
     * Seller only sees their own payments; Super Admin sees all payments.
     */
    public function listingPaymentsHistory(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();

        $query = ProductListingPayment::with(['product:id,name,sku,image', 'user:id,name,email,store_name'])
            ->latest();

        if (! $isSuperAdmin) {
            $query->where('user_id', $user->id);
        }

        $payments = $query->paginate(20);

        return response()->json([
            'success' => true,
            'payments' => $payments,
            'is_super_admin' => $isSuperAdmin,
        ]);
    }

    /**
     * Render the dedicated Listing Payments History page.
     */
    public function listingPaymentsIndex(Request $request)
    {
        if (! config('app.is_seller', false)) {
            return redirect()->route('admin.products.index');
        }

        $user = $request->user();
        $isSuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();

        $query = ProductListingPayment::with(['product:id,name,sku,image', 'user:id,name,email,store_name'])
            ->latest('id');

        if (! $isSuperAdmin) {
            $query->where('user_id', $user->id);
        }

        $status = $request->input('status', 'all');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $search = trim($request->input('q', ''));
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($qp) => $qp->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($qu) => $qu->where('name', 'like', "%{$search}%")->orWhere('store_name', 'like', "%{$search}%"));
            });
        }

        $dateFrom = $request->input('date_from', '');
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        $dateTo = $request->input('date_to', '');
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $payments = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Products/ListingPayments', [
            'payments' => $payments,
            'isSuperAdmin' => $isSuperAdmin,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get complete listing pricing configuration including dynamic packages.
     */
    private function getListingPricingConfig(): array
    {
        $rawPackages = Setting::where('key', 'product_listing_packages')->value('value');
        $packages = $rawPackages ? (json_decode($rawPackages, true) ?? []) : [];
        if (empty($packages)) {
            $packages = [
                ['id' => 'pkg_15', 'name' => 'Paket 15 Hari', 'days' => 15, 'price' => 15000, 'is_popular' => false],
                ['id' => 'pkg_30', 'name' => 'Paket 30 Hari', 'days' => 30, 'price' => 30000, 'is_popular' => true],
            ];
        }

        return [
            'daily_rate' => (float) (Setting::where('key', 'product_listing_daily_rate')->value('value') ?? 1000),
            'max_custom_days' => (int) (Setting::where('key', 'product_listing_max_custom_days')->value('value') ?? 365),
            'custom_daily_rate' => (float) (Setting::where('key', 'product_listing_custom_daily_rate')->value('value') ?? 1000),
            'fee_enabled' => (bool) (Setting::where('key', 'product_listing_fee_enabled')->value('value') ?? true),
            'packages' => $packages,
        ];
    }

    /**
     * Calculate days and fee based on request selection and config.
     */
    private function calculateListingFeeAndDays(Request $request, array $config): array
    {
        $type = $request->input('listing_duration_type');
        $packages = $config['packages'] ?? [];

        $matched = null;
        if ($type) {
            foreach ($packages as $pkg) {
                if (($pkg['id'] ?? null) === $type || (string) ($pkg['days'] ?? '') === (string) $type) {
                    $matched = $pkg;
                    break;
                }
            }
        }

        if (! $matched && ! empty($packages) && $type !== 'custom') {
            $matched = $packages[0];
        }

        if ($matched) {
            return [
                'days' => (int) $matched['days'],
                'fee' => (float) $matched['price'],
            ];
        }

        $days = min(max(1, (int) $request->input('custom_days', 1)), $config['max_custom_days']);
        $fee = $days * $config['custom_daily_rate'];

        return [
            'days' => $days,
            'fee' => $fee,
        ];
    }
}
