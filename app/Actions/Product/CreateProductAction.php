<?php

namespace App\Actions\Product;

use App\Helpers\ImageHelper;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateProductAction
{
    /**
     * Create product with relations, images, variants, stocks, and pricing.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $extraInputs
     */
    public function execute(array $validated, array $extraInputs, ?User $user): Product
    {
        return DB::transaction(function () use ($validated, $extraInputs, $user) {
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

            $videoPath = $extraInputs['video_url'] ?? null;
            if (! empty($extraInputs['video_file'])) {
                $path = $extraInputs['video_file']->store('products/videos', 'public');
                $videoPath = 'storage/'.$path;
            }
            $validated['video_path'] = $videoPath;

            $modelPath = $extraInputs['model_3d_url'] ?? null;
            if (! empty($extraInputs['model_3d_file'])) {
                $filename = Str::random(40).'.glb';
                $path = $extraInputs['model_3d_file']->storeAs('products/models', $filename, 'public');
                $modelPath = 'storage/'.$path;
            }
            $validated['model_3d_path'] = $modelPath;

            $usdzPath = $extraInputs['model_3d_usdz_url'] ?? null;
            if (! empty($extraInputs['model_3d_usdz_file'])) {
                $filename = Str::random(40).'.usdz';
                $path = $extraInputs['model_3d_usdz_file']->storeAs('products/models', $filename, 'public');
                $usdzPath = 'storage/'.$path;
            }
            $validated['model_3d_usdz_path'] = $usdzPath;

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
                'new_seller_name',
                'new_seller_phone',
                'new_seller_email',
                'new_seller_password',
            ]);

            $isAdmin = $user?->hasAnyRole(['Super Admin', 'Admin']) || ! $user?->is_seller;

            if ($isAdmin && ! empty($extraInputs['new_seller']['name'])) {
                $sellerUser = $this->findOrCreateSeller($extraInputs['new_seller']);
                $productData['user_id'] = $sellerUser->id;
                if (empty($productData['contact_name'])) {
                    $productData['contact_name'] = $sellerUser->name;
                }
                if (empty($productData['contact_phone'])) {
                    $productData['contact_phone'] = $sellerUser->phone_number;
                }
            } elseif ($isAdmin && ! empty($validated['user_id'])) {
                $productData['user_id'] = $validated['user_id'];
            } else {
                $productData['user_id'] = $user?->id;
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

            $product->categories()->sync($categoryIds);
            $product->brands()->sync($brandIds);

            // Master Price & Stock
            $product->productPrice()->create([
                'price' => $validated['price'],
                'cost' => $validated['cost'] ?? null,
            ]);

            $product->productStock()->create([
                'stock' => $validated['stock'],
                'min_stock' => $validated['min_stock'] ?? 0,
                'min_purchase' => $validated['min_purchase'] ?? 1,
                'is_unlimited' => $validated['is_unlimited'] ?? false,
            ]);

            // Tier prices
            if (! empty($validated['tier_prices'])) {
                foreach ($validated['tier_prices'] as $tp) {
                    $product->tierPrices()->create([
                        'min_qty' => $tp['min_qty'],
                        'price' => $tp['price'],
                    ]);
                }
            }

            // Photos
            if (! empty($validated['photos'])) {
                foreach ($validated['photos'] as $index => $photoBase64) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $photoBase64, $type)) {
                        $rawBase64 = substr($photoBase64, strpos($photoBase64, ',') + 1);
                        $ext = strtolower($type[1]);
                        $decoded = base64_decode(str_replace(' ', '+', $rawBase64));
                        $compressed = ImageHelper::compress($decoded, $ext, 75);
                        $filename = 'product_'.$product->id.'_'.time().'_'.$index.'.'.$ext;
                        Storage::disk('public')->put('products/'.$filename, $compressed);

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

            // Variations & Variants
            $variationsInput = $extraInputs['variations'] ?? [];
            $variantsInput = $extraInputs['variants'] ?? [];

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
                            $ext = strtolower($type[1]);
                            $decoded = base64_decode(str_replace(' ', '+', $imgBase64));
                            $compressed = ImageHelper::compress($decoded, $ext, 75);
                            $filename = 'opt_'.$product->id.'_'.time().'_'.uniqid().'.'.$ext;
                            Storage::disk('public')->put('products/'.$filename, $compressed);
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
                        $hasCustomPrice = ! empty($vCombData['is_custom']) && ! empty($vCombData['custom_price']);
                        $hasCustomStock = ! empty($vCombData['is_custom']) && ! empty($vCombData['custom_stock']);
                        $hasCustomWeight = ! empty($vCombData['is_custom']) && ! empty($vCombData['custom_weight']);
                        $rawVariantSku = trim($vCombData['sku'] ?? '');
                        if (empty($rawVariantSku)) {
                            $rawVariantSku = $product->sku.'-VAR-'.Str::random(4);
                        }
                        $variantSku = $rawVariantSku;
                        $varCount = 1;
                        while (ProductVariant::where('sku', $variantSku)->exists()) {
                            $variantSku = $rawVariantSku.'-'.$varCount;
                            $varCount++;
                        }

                        $variant = $product->variants()->create([
                            'sku' => $variantSku,
                            'weight' => $hasCustomWeight ? (int) ($vCombData['weight'] ?? 0) : ($validated['weight'] ?? 0),
                            'length' => $hasCustomWeight ? (int) ($vCombData['length'] ?? 0) : ($validated['length'] ?? 0),
                            'width' => $hasCustomWeight ? (int) ($vCombData['width'] ?? 0) : ($validated['width'] ?? 0),
                            'height' => $hasCustomWeight ? (int) ($vCombData['height'] ?? 0) : ($validated['height'] ?? 0),
                            'is_active' => $vCombData['is_active'] ?? true,
                        ]);

                        if ($hasCustomPrice) {
                            $variant->productPrice()->create([
                                'product_id' => $product->id,
                                'price' => $vCombData['price'] ?? 0,
                                'cost' => $vCombData['cost'] ?? null,
                            ]);
                        }

                        if ($hasCustomStock) {
                            $variant->productStock()->create([
                                'product_id' => $product->id,
                                'stock' => $vCombData['stock'] ?? 0,
                                'min_stock' => $vCombData['min_stock'] ?? 0,
                                'min_purchase' => $vCombData['min_purchase'] ?? 1,
                                'is_unlimited' => $vCombData['is_unlimited'] ?? false,
                            ]);
                        }

                        $frontendOptionIds = explode('_', (string) $vCombData['id']);
                        $realOptionIds = array_values(array_filter(array_map(
                            fn (string $frontendOptionId) => $variationMap[$frontendOptionId] ?? null,
                            $frontendOptionIds,
                        )));

                        $variant->options()->sync($realOptionIds);
                    }
                }
            }

            return $product;
        });
    }

    private function canonicalPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    private function findUserByPhoneVariations(string $phone): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        $variations = array_filter(array_unique([
            trim($phone),
            $digits,
            $this->canonicalPhone($digits),
            str_starts_with($digits, '0') ? '62'.substr($digits, 1) : null,
            str_starts_with($digits, '62') ? '0'.substr($digits, 2) : null,
            str_starts_with($digits, '8') ? '08'.substr($digits, 1) : null,
            str_starts_with($digits, '8') ? '628'.substr($digits, 1) : null,
        ]));

        return User::whereIn('phone_number', $variations)
            ->orderByDesc('is_seller')
            ->orderByDesc('created_at')
            ->first();
    }

    private function findOrCreateSeller(array $newSellerData): User
    {
        $existingUser = null;
        $phone = trim($newSellerData['phone_number'] ?? $newSellerData['phone'] ?? '');
        $email = trim($newSellerData['email'] ?? '');
        $name = trim($newSellerData['name'] ?? 'Seller');

        if (! empty($phone)) {
            $existingUser = $this->findUserByPhoneVariations($phone);
        }

        if (! $existingUser && ! empty($email)) {
            $existingUser = User::where('email', $email)->orderByDesc('is_seller')->orderByDesc('created_at')->first();
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

            return $existingUser;
        }

        $sellerEmail = ! empty($email)
            ? $email
            : ('seller_'.time().'_'.Str::random(4).'@bizmate.local');

        $rawSlug = Str::slug($name);
        $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
        while (User::where('store_slug', $storeSlug)->exists()) {
            $storeSlug = $rawSlug.'-'.Str::lower(Str::random(4));
        }

        return User::create([
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
}
