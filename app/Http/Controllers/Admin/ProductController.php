<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariationOption;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with([
            'category',
            'productPrice',
            'productStock',
            'tierPrices',
            'variants.options',
            'variants.productPrice',
            'variants.productStock',
            'variants.tierPrices',
            'variations.options',
        ])->latest();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->get('category') !== 'all') {
            $query->where('category_id', $request->get('category'));
        }

        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('active', $request->get('status') === 'active');
        }

        $products = $query->paginate(10)->withQueryString();

        $categories = Category::select('id', 'name')->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    public function create()
    {
        $categories = Category::select('id', 'name')->get();

        return Inertia::render('Admin/Products/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Product store request payload', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'is_unlimited' => 'boolean',
            'stock_status' => 'nullable|string',
            'summary' => 'nullable|string|max:255',
            'description' => 'required|string',
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
        ]);

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $validated['stock'] = $validated['stock'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['length'] = $validated['length'] ?? 0;
        $validated['width'] = $validated['width'] ?? 0;
        $validated['height'] = $validated['height'] ?? 0;

        // Remove price/stock fields from product data
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
        ]);

        $product = Product::create($productData);

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
                    $filename = 'product_'.$product->id.'_'.time().'_'.$index.'.'.$type;
                    Storage::disk('public')->put('products/'.$filename, $photoBase64);

                    $product->images()->create([
                        'path' => 'storage/products/'.$filename,
                        'is_main' => $index === 0,
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
            foreach ($variationsInput as $vData) {
                $variation = $product->variations()->create(['name' => $vData['name']]);
                foreach ($vData['options'] as $optData) {
                    $imagePath = null;
                    if (! empty($optData['image']) && preg_match('/^data:image\/(\w+);base64,/', $optData['image'], $type)) {
                        $imgBase64 = substr($optData['image'], strpos($optData['image'], ',') + 1);
                        $type = strtolower($type[1]);
                        $imgBase64 = base64_decode(str_replace(' ', '+', $imgBase64));
                        $filename = 'opt_'.$product->id.'_'.time().'_'.uniqid().'.'.$type;
                        Storage::disk('public')->put('products/'.$filename, $imgBase64);
                        $imagePath = 'storage/products/'.$filename;
                    }

                    $option = $variation->options()->create([
                        'name' => $optData['name'],
                        'description' => $optData['description'] ?? null,
                        'image' => $imagePath,
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

        return redirect()->route('admin.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $categories = Category::select('id', 'name')->get();
        // Eager load variations, images, prices, stocks
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
        ]);

        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        Log::info('Product update request payload', $request->all());
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku,'.$product->id,
            'category_id' => 'required|exists:categories,id',
            'brand' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'is_unlimited' => 'boolean',
            'stock_status' => 'nullable|string',
            'summary' => 'nullable|string|max:255',
            'description' => 'required|string',
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
        ]);

        if ($product->name !== $validated['name']) {
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
        }
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $validated['stock'] = $validated['stock'] ?? 0;
        $validated['weight'] = $validated['weight'] ?? 0;
        $validated['length'] = $validated['length'] ?? 0;
        $validated['width'] = $validated['width'] ?? 0;
        $validated['height'] = $validated['height'] ?? 0;

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
        ]);

        $product->update($productData);

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

        foreach ($variationsInput as $vData) {
            // Find existing variation or create a new one
            $variation = null;
            if (! empty($vData['id']) && is_numeric($vData['id'])) {
                $variation = $product->variations()->find($vData['id']);
            }

            if ($variation) {
                $variation->update(['name' => $vData['name']]);
            } else {
                $variation = $product->variations()->create(['name' => $vData['name']]);
            }
            $keptVariationIds[] = $variation->id;

            // Process options of this variation
            if (! empty($vData['options'])) {
                foreach ($vData['options'] as $optData) {
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
                        ]);
                    } else {
                        $option = $variation->options()->create([
                            'name' => $optData['name'],
                            'description' => $optData['description'] ?? null,
                            'image' => $imagePath,
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

        return redirect()->route('admin.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
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
}
