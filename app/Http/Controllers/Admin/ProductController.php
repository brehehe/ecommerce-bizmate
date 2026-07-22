<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Jobs\ImportProductsJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariationOption;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with([
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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
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

        $query->withSum(['transactionItems as total_qty_sold' => function ($q) {
            $q->whereHas('transaction', function ($t) {
                $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
            });
        }], 'quantity');

        $query->withSum(['transactionItems as total_revenue' => function ($q) {
            $q->whereHas('transaction', function ($t) {
                $t->whereIn('status', ['diproses', 'dikemas', 'dikirim', 'selesai']);
            });
        }], 'subtotal');

        $query->withSum(['returnItems as total_qty_returned' => function ($q) {
            $q->whereHas('returnRequest', function ($r) {
                $r->where('status', 'selesai');
            });
        }], 'quantity_returned');

        $query->withSum(['returnItems as total_refund_amount' => function ($q) {
            $q->whereHas('returnRequest', function ($r) {
                $r->where('status', 'selesai');
            });
        }], 'refund_subtotal');

        $perPage = $request->get('per_page', 10);
        if ($perPage === 'all') {
            $products = $query->paginate(999999)->withQueryString();
        } else {
            $products = $query->paginate((int) $perPage)->withQueryString();
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

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'import_auto_fetch_images' => (bool) config('services.products.import_auto_fetch_images', true),
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
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

    public function create()
    {
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
            'brands' => $brands,
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
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
            'video_file' => 'nullable|file|mimes:mp4,mov,webm,qt|max:51200',
            'model_3d_url' => 'nullable|string',
            'model_3d_file' => 'nullable|file|max:51200',
            'model_3d_usdz_url' => 'nullable|string',
            'model_3d_usdz_file' => 'nullable|file|max:51200',
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
        ]);

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
                    $variant = $product->variants()->create([
                        'sku' => $vCombData['sku'],
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

    public function edit(Product $product)
    {
        $categories = Category::select('id', 'name')->get();
        $brands = Brand::orderBy('name')->get();
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
        ]);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'ai_enabled' => (bool) config('services.openagentic.enabled', false),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        Log::info('Product update request payload', $request->all());

        if ($request->has('category_id') && ! $request->has('category_ids')) {
            $request->merge(['category_ids' => [$request->input('category_id')]]);
        }
        if ($request->has('brand_id') && ! $request->has('brand_ids')) {
            $request->merge(['brand_ids' => array_filter([$request->input('brand_id')])]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,'.$product->id,
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
            'video_file' => 'nullable|file|mimes:mp4,mov,webm,qt|max:51200',
            'model_3d_url' => 'nullable|string',
            'model_3d_file' => 'nullable|file|max:51200',
            'model_3d_usdz_url' => 'nullable|string',
            'model_3d_usdz_file' => 'nullable|file|max:51200',
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
        ]);

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
            if (! empty($vData['id']) && is_numeric($vData['id'])) {
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
                    if (! empty($optData['id']) && is_numeric($optData['id'])) {
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
                    $optionIdMap[$optData['id']] = $option->id;
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
                    } elseif (is_numeric($fid)) {
                        $dbOptionIds[] = (int) $fid;
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
                    // If the new SKU is already taken by a DIFFERENT variant, keep the current SKU.
                    $newSku = $variantData['sku'];
                    $skuConflict = ProductVariant::where('sku', $newSku)
                        ->where('id', '!=', $matchedVariant->id)
                        ->exists();

                    if ($skuConflict) {
                        // Preserve the existing SKU to avoid unique constraint violation.
                        unset($variantData['sku']);
                    }

                    // Update existing
                    $matchedVariant->update($variantData);
                    $variant = $matchedVariant;
                } else {
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

    public function destroy(Product $product)
    {
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

    public function toggleActive(Product $product)
    {
        $product->update(['active' => ! $product->active]);

        return redirect()->back()->with('success', 'Product status updated.');
    }

    public function managePrices(Request $request)
    {
        $search = $request->input('search');
        $query = Product::with([
            'category',
            'productPrice',
            'tierPrices',
            'variants.options',
            'variants.productPrice',
            'variants.tierPrices',
        ])->latest();

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

        \DB::transaction(function () use ($request) {
            foreach ($request->input('products') as $pData) {
                $product = Product::findOrFail($pData['id']);
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
        $query = Product::with([
            'category',
            'productStock',
            'variants.options',
            'variants.productStock',
        ])->latest();

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

        \DB::transaction(function () use ($request) {
            foreach ($request->input('products') as $pData) {
                $product = Product::findOrFail($pData['id']);

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
        $query = Product::with([
            'category',
            'variants.options',
        ])->latest();

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

        \DB::transaction(function () use ($request) {
            foreach ($request->input('products') as $pData) {
                $product = Product::findOrFail($pData['id']);
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

    public function exportProducts()
    {
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

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write the Excel separator instruction
            fwrite($file, "sep=,\n");

            fputcsv($file, $columns);

            Product::with([
                'categories',
                'brandRelation',
                'productPrice',
                'productStock',
                'variations.options',
                'variants.options',
                'variants.productPrice',
                'variants.productStock',
            ])->chunk(100, function ($products) use ($file) {
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

        try {
            ImportProductsJob::dispatch($request->input('products'), $autoFetch);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Gagal menjadwalkan import produk: '.$e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['error' => 'Gagal menjadwalkan import produk: '.$e->getMessage()]);
        }

        $count = count($request->input('products'));
        $successMessage = "Import {$count} produk telah dijadwalkan di latar belakang (Supervisor). Proses ini akan mengimpor data & melengkapi foto produk secara otomatis.";

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
                    if ($decodedSize > 2 * 1024 * 1024) {
                        throw ValidationException::withMessages([
                            'photos' => ['Ukuran gambar produk maksimal 2MB.'],
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
                            if ($decodedSize > 2 * 1024 * 1024) {
                                throw ValidationException::withMessages([
                                    'variations' => ['Ukuran gambar opsi varian maksimal 2MB.'],
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
}
