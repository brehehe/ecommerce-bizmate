<?php

namespace App\Jobs;

use App\Helpers\ImageHelper;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ImageSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProductsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800; // 30 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $products,
        public bool $autoFetchImages = true
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $globalTaxPercentage = (float) Setting::where('key', 'tax_percentage')->value('value') ?: 10;
        $importedProducts = [];

        try {
            DB::transaction(function () use ($globalTaxPercentage, &$importedProducts) {
                foreach ($this->products as $pData) {
                    $sku = trim($pData['sku']);
                    $slug = Str::slug($pData['name']).'-'.Str::random(5);

                    $product = Product::where('sku', $sku)->first();
                    if ($product) {
                        $product->update([
                            'name' => $pData['name'],
                            'summary' => $pData['summary'] ?? null,
                            'description' => $pData['description'],
                            'weight' => $pData['weight'] ?? 0,
                            'length' => $pData['length'] ?? 0,
                            'width' => $pData['width'] ?? 0,
                            'height' => $pData['height'] ?? 0,
                            'tax_enabled' => ! empty($pData['tax_enabled']),
                            'tax_rate' => ! empty($pData['tax_enabled']) ? $globalTaxPercentage : 0,
                            'is_digital' => ! empty($pData['is_digital']),
                            'specifications' => $pData['specifications'] ?? null,
                        ]);
                    } else {
                        $product = Product::create([
                            'name' => $pData['name'],
                            'sku' => $sku,
                            'slug' => $slug,
                            'summary' => $pData['summary'] ?? null,
                            'description' => $pData['description'],
                            'weight' => $pData['weight'] ?? 0,
                            'length' => $pData['length'] ?? 0,
                            'width' => $pData['width'] ?? 0,
                            'height' => $pData['height'] ?? 0,
                            'tax_enabled' => ! empty($pData['tax_enabled']),
                            'tax_rate' => ! empty($pData['tax_enabled']) ? $globalTaxPercentage : 0,
                            'is_digital' => ! empty($pData['is_digital']),
                            'active' => true,
                            'specifications' => $pData['specifications'] ?? null,
                        ]);
                    }

                    // Sync categories
                    $categoryIds = [];
                    if (! empty($pData['category_names'])) {
                        $names = explode(',', $pData['category_names']);
                        foreach ($names as $name) {
                            $name = trim($name);
                            if (! $name) {
                                continue;
                            }
                            $category = Category::whereRaw('lower(name) = ?', [strtolower($name)])->first();
                            if (! $category) {
                                $baseSlug = Str::slug($name);
                                $catSlug = $baseSlug;
                                $count = 1;
                                while (Category::where('slug', $catSlug)->exists()) {
                                    $catSlug = $baseSlug.'-'.$count;
                                    $count++;
                                }
                                $category = Category::create([
                                    'name' => $name,
                                    'slug' => $catSlug,
                                    'icon' => 'ti-tag',
                                    'order' => 0,
                                ]);
                            }
                            $categoryIds[] = $category->id;
                        }
                    }
                    if (! empty($categoryIds)) {
                        $product->categories()->sync($categoryIds);
                        $product->update(['category_id' => $categoryIds[0]]);
                    }

                    // Sync brand
                    $brandIds = [];
                    if (! empty($pData['brand_name'])) {
                        $bName = trim($pData['brand_name']);
                        if ($bName) {
                            $brand = Brand::whereRaw('lower(name) = ?', [strtolower($bName)])->first();
                            if (! $brand) {
                                $baseSlug = Str::slug($bName);
                                $bSlug = $baseSlug;
                                $count = 1;
                                while (Brand::where('slug', $bSlug)->exists()) {
                                    $bSlug = $baseSlug.'-'.$count;
                                    $count++;
                                }
                                $brand = Brand::create([
                                    'name' => $bName,
                                    'slug' => $bSlug,
                                    'is_active' => true,
                                    'order' => 0,
                                ]);
                            }
                            $brandIds[] = $brand->id;
                            $product->update([
                                'brand_id' => $brand->id,
                                'brand' => $brand->name,
                            ]);
                        }
                    }
                    $product->brands()->sync($brandIds);

                    // Main price
                    $product->productPrice()->updateOrCreate(
                        ['product_variant_id' => null],
                        [
                            'price' => $pData['price'],
                            'cost' => $pData['cost'] ?? null,
                        ]
                    );

                    // Main stock
                    $product->productStock()->updateOrCreate(
                        ['product_variant_id' => null],
                        [
                            'stock' => $pData['stock'] ?? 0,
                            'min_stock' => $pData['min_stock'] ?? 0,
                            'min_purchase' => $pData['min_purchase'] ?? 1,
                            'is_unlimited' => ! empty($pData['is_unlimited']),
                        ]
                    );

                    // Variations / Variants
                    if (! empty($pData['variations'])) {
                        $product->variants()->each(function ($v) {
                            $v->productPrice()->delete();
                            $v->productStock()->delete();
                            $v->options()->detach();
                            $v->delete();
                        });
                        $product->variations()->each(function ($var) {
                            $var->options()->delete();
                            $var->delete();
                        });

                        $variationMap = [];
                        foreach ($pData['variations'] as $vData) {
                            $variation = $product->variations()->create(['name' => $vData['name']]);
                            foreach ($vData['options'] as $optData) {
                                $option = $variation->options()->create([
                                    'name' => $optData['name'],
                                    'description' => null,
                                    'image' => null,
                                ]);
                                $variationMap[$optData['name']] = $option->id;
                            }
                        }

                        if (! empty($pData['variants'])) {
                            foreach ($pData['variants'] as $vCombData) {
                                $variant = $product->variants()->create([
                                    'sku' => $vCombData['sku'],
                                    'weight' => null,
                                    'length' => null,
                                    'width' => null,
                                    'height' => null,
                                    'image' => null,
                                ]);

                                if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_price'])) {
                                    $variant->productPrice()->create([
                                        'product_id' => $product->id,
                                        'price' => $vCombData['price'] ?: 0,
                                        'cost' => $vCombData['cost'] ?: null,
                                    ]);
                                }

                                if (! empty($vCombData['is_custom']) && ! empty($vCombData['custom_stock'])) {
                                    $variant->productStock()->create([
                                        'product_id' => $product->id,
                                        'stock' => $vCombData['stock'] ?: 0,
                                        'min_stock' => 0,
                                        'min_purchase' => 1,
                                        'is_unlimited' => false,
                                    ]);
                                }

                                $frontNames = explode('_', $vCombData['id']);
                                $optionIdsToAttach = [];
                                foreach ($frontNames as $optName) {
                                    if (isset($variationMap[$optName])) {
                                        $optionIdsToAttach[] = $variationMap[$optName];
                                    }
                                }
                                $variant->options()->attach($optionIdsToAttach);
                            }
                        }
                    }

                    $importedProducts[] = $product;
                }
            });

            // Automatic image fetching during import is completely disabled to avoid irrelevant/incorrect images.
        } catch (\Throwable $e) {
            Log::error('Background product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
