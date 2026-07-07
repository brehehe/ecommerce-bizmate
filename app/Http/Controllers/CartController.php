<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Setting;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CartController extends Controller
{
    private ?array $promoItemsCache = null;

    private ?array $soldPromoQuantitiesCache = null;

    /**
     * Show the cart page.
     */
    public function index(Request $request)
    {
        session()->forget('buy_now_item');

        $cartItems = CartItem::with([
            'product.productPrice',
            'product.productStock',
            'product.images',
            'product.category',
            'product.variants.productPrice',
            'product.variants.productStock',
            'product.variants.options',
            'product.variants.tierPrices',
            'productVariant.productPrice',
            'productVariant.productStock',
            'productVariant.options',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $activePromotions = Promotion::with(['items'])
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->get();

        // Populate product info for bundling_gift promotions so the frontend has access to names, images, etc.
        $bundlingProductIds = [];
        foreach ($activePromotions as $promo) {
            if ($promo->type === 'bundling_gift' && isset($promo->settings['bundle'])) {
                $bundle = $promo->settings['bundle'];
                if (isset($bundle['buy_items'])) {
                    foreach ($bundle['buy_items'] as $buyItem) {
                        if (! empty($buyItem['product_id'])) {
                            $bundlingProductIds[] = $buyItem['product_id'];
                        }
                    }
                }
                if (isset($bundle['get_items'])) {
                    foreach ($bundle['get_items'] as $getItem) {
                        if (! empty($getItem['product_id'])) {
                            $bundlingProductIds[] = $getItem['product_id'];
                        }
                    }
                }
            }
        }
        $bundlingProductIds = array_unique($bundlingProductIds);

        $productsMap = [];
        if (! empty($bundlingProductIds)) {
            $productsMap = Product::with(['productPrice', 'images'])
                ->whereIn('id', $bundlingProductIds)
                ->get()
                ->keyBy('id');
        }

        $activePromotions->each(function ($promo) use ($productsMap) {
            if ($promo->type === 'bundling_gift' && isset($promo->settings['bundle'])) {
                $bundle = $promo->settings['bundle'];

                // Load buy_items products
                if (isset($bundle['buy_items'])) {
                    foreach ($bundle['buy_items'] as &$buyItem) {
                        if (! empty($buyItem['product_id'])) {
                            $prod = $productsMap->get($buyItem['product_id']);
                            if ($prod) {
                                $buyItem['product_name'] = $prod->name;
                                $buyItem['product_slug'] = $prod->slug;
                                $buyItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $buyItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                // Load get_items products
                if (isset($bundle['get_items'])) {
                    foreach ($bundle['get_items'] as &$getItem) {
                        if (! empty($getItem['product_id'])) {
                            $prod = $productsMap->get($getItem['product_id']);
                            if ($prod) {
                                $getItem['product_name'] = $prod->name;
                                $getItem['product_slug'] = $prod->slug;
                                $getItem['product_image'] = $prod->images->first()?->url ?? $prod->images->first()?->path ?? $prod->image;
                                $getItem['product_price'] = (float) ($prod->productPrice?->price ?? 0);
                            }
                        }
                    }
                }

                $promo->settings = array_merge($promo->settings, ['bundle' => $bundle]);
            }
        });

        // Calculate pricing dynamically for each cart item (applying any promotions or tier prices)
        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            // Apply promotions first
            $this->applyPromotionsToProduct($product, $activePromotions, $item->quantity);

            if ($variant) {
                // Find matching variant from loaded product variants to get applied promotion fields
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $variant = $matchedVariant;
                    $item->setRelation('productVariant', $matchedVariant);
                }
            }

            // Get base price
            $basePrice = 0;
            $stock = 0;
            $isUnlimited = false;

            if ($variant) {
                $basePrice = (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0));
                $stock = (int) ($variant->productStock?->stock ?? 0);
                $isUnlimited = (bool) ($variant->productStock?->is_unlimited ?? false);

                // Fetch tier prices for variant
                $tiers = $variant->tierPrices;
            } else {
                $basePrice = (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));
                $stock = (int) ($product->productStock?->stock ?? 0);
                $isUnlimited = (bool) ($product->productStock?->is_unlimited ?? false);

                // Fetch tier prices for product
                $tiers = $product->tierPrices;
            }

            // Find applicable tier price
            $unitPrice = $basePrice;
            $keepTierPrices = $variant ? ($variant->keep_tier_prices ?? false) : ($product->keep_tier_prices ?? false);
            $isOnPromo = $variant ? ($variant->is_promo ?? false) : ($product->is_promo ?? false);

            if ((! $isOnPromo || $keepTierPrices) && $tiers && $tiers->isNotEmpty()) {
                $sortedTiers = $tiers->sortByDesc('min_qty');
                $matchingTier = $sortedTiers->first(function ($t) use ($item) {
                    return $item->quantity >= $t->min_qty;
                });
                if ($matchingTier) {
                    $unitPrice = (float) $matchingTier->price;
                }
            }

            $item->unit_price = $unitPrice;
            $item->subtotal = $unitPrice * $item->quantity;
            $item->stock = $stock;
            $item->is_unlimited = $isUnlimited;
        }

        $cartPromoPrices = [];
        foreach ($cartItems as $item) {
            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $cartPromoPrices[$key] = [
                'price' => (float) $item->unit_price,
                'is_promo' => (bool) ($item->productVariant ? ($item->productVariant->is_promo ?? false) : ($item->product->is_promo ?? false)),
            ];
        }
        session(['cart_promo_prices' => $cartPromoPrices]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Cart', [
            'cartItems' => $cartItems,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'activePromotions' => $activePromotions,
        ]);
    }

    /**
     * Add a product/variant to the cart.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'buy_now' => 'nullable|boolean',
        ]);

        $userId = $request->user()->id;
        $productId = $request->input('product_id');
        $variantId = $request->input('product_variant_id');
        $quantity = (int) $request->input('quantity');
        $buyNow = $request->boolean('buy_now');

        // Load product with stock information for validation
        $product = Product::with([
            'productStock',
            'variants.productStock',
        ])->findOrFail($productId);

        // Resolve the relevant stock record (variant or product level)
        if ($variantId) {
            $variant = $product->variants->firstWhere('id', $variantId);
            $stockRecord = $variant?->productStock;
            $productLabel = $product->name.($variant ? ' ('.($variant->sku ?? 'varian ini').')' : '');
        } else {
            $stockRecord = $product->productStock;
            $productLabel = $product->name;
        }

        // Validate stock availability before adding to cart
        if ($stockRecord && ! $stockRecord->is_unlimited) {
            // Check if this combination already exists in the cart
            $existingCartItem = CartItem::where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            $existingQty = $existingCartItem?->quantity ?? 0;
            $totalQty = $buyNow ? $quantity : ($existingQty + $quantity);

            if ($stockRecord->stock <= 0) {
                return back()->with('error', "Stok {$productLabel} sudah habis.");
            }

            if ($totalQty > $stockRecord->stock) {
                if ($buyNow) {
                    return back()->with('error', "Stok {$productLabel} tidak mencukupi untuk jumlah yang Anda beli.");
                }

                $available = max(0, $stockRecord->stock - $existingQty);
                if ($available <= 0) {
                    return back()->with('error', "Stok {$productLabel} sudah mencapai batas maksimal di keranjang Anda.");
                }

                return back()->with('error', "Stok {$productLabel} tidak mencukupi. Anda bisa menambah {$available} pcs lagi.");
            }
        }

        if ($buyNow) {
            session([
                'buy_now_item' => [
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ],
            ]);

            return redirect()->route('checkout.index');
        }

        // Check if this combination already exists in the cart
        $cartItem = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->is_checked = true;
            $cartItem->save();
        } else {
            CartItem::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'is_checked' => true,
            ]);
        }

        return redirect()->back()->with('success', 'Produk berhasil ditambahkan ke keranjang!');
    }

    /**
     * Update quantity of a cart item.
     */
    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'is_checked' => 'nullable|boolean',
        ]);

        if ($cartItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = [];
        if ($request->has('quantity')) {
            $data['quantity'] = $request->input('quantity');
        }

        if ($request->has('is_checked')) {
            $data['is_checked'] = (bool) $request->input('is_checked');
        }

        if ($request->has('product_variant_id')) {
            $newVariantId = $request->input('product_variant_id');
            // If switched to the same variant, do nothing
            if ($cartItem->product_variant_id !== $newVariantId) {
                // Check if there is already another item in the cart with this variant
                $existing = CartItem::where('user_id', $cartItem->user_id)
                    ->where('product_id', $cartItem->product_id)
                    ->where('product_variant_id', $newVariantId)
                    ->where('id', '!=', $cartItem->id)
                    ->first();

                if ($existing) {
                    // Merge quantities and delete current item
                    $existing->quantity += $cartItem->quantity;
                    // If either was checked, keep it checked
                    $existing->is_checked = $existing->is_checked || ($data['is_checked'] ?? $cartItem->is_checked);
                    $existing->save();
                    $cartItem->delete();

                    return redirect()->back()->with('success', 'Keranjang digabungkan karena produk sejenis sudah ada!');
                }

                $data['product_variant_id'] = $newVariantId;
            }
        }

        $cartItem->update($data);

        return redirect()->back();
    }

    /**
     * Bulk update check status of cart items.
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'is_checked' => 'required|boolean',
            'ids' => 'nullable|array',
            'ids.*' => 'uuid|exists:cart_items,id',
        ]);

        $query = CartItem::where('user_id', $request->user()->id);

        if ($request->has('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        $query->update([
            'is_checked' => (bool) $request->input('is_checked'),
        ]);

        return redirect()->back();
    }

    /**
     * Remove a cart item.
     */
    public function destroy(Request $request, CartItem $cartItem)
    {
        if ($cartItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $cartItem->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus dari keranjang!');
    }

    /**
     * Helper to apply active promotions on a product and its variants.
     */
    private function applyPromotionsToProduct(Product $product, $activePromotions, int $quantity = 1): void
    {
        $basePrice = (float) ($product->productPrice?->price ?? 0);

        // Resolve promotion candidates once
        // 1. Flash Sale
        $flashSalePromo = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'flash_sale') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true; // Store-wide
            }
            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        // 2. Bundling Gift
        $isBundlingActive = $activePromotions->contains(function ($promo) use ($product) {
            if ($promo->type !== 'bundling_gift') {
                return false;
            }
            $bundle = $promo->settings['bundle'] ?? null;
            if (! $bundle || empty($bundle['buy_items'])) {
                return false;
            }
            foreach ($bundle['buy_items'] as $bi) {
                if ($bi['product_id'] == $product->id) {
                    return true;
                }
            }
            return false;
        });

        // 3. Promo Produk
        $promoProduk = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'promo_produk') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }
            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        // 4. Promo Toko / Special Deals
        $promoToko = $activePromotions->first(function ($promo) use ($product) {
            if ($promo->type !== 'promo_toko' && $promo->type !== 'special_deals') {
                return false;
            }
            if ($promo->items->isEmpty()) {
                return true;
            }
            return $promo->items->contains(function ($i) use ($product) {
                return $i->product_id === $product->id;
            });
        });

        $appliedPromo = null;
        $appliedItem = null;
        $remainingStock = null;

        // Clear out any previous promo rule
        $product->promo_rule = null;
        $product->applied_promotion_id = null;
        $product->promo_quantity_used = null;

        // 1. Try Flash Sale
        if ($flashSalePromo) {
            $fsRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, null);
            if (! is_null($fsRemainingStock)) {
                $product->promo_rule = [
                    'id' => $flashSalePromo->id,
                    'name' => $flashSalePromo->name,
                    'type' => $flashSalePromo->type,
                    'min_qty' => 1,
                    'remaining_promo_stock' => $fsRemainingStock,
                ];
            }

            if (is_null($fsRemainingStock) || $fsRemainingStock > 0) {
                $appliedPromo = $flashSalePromo;
                $remainingStock = $fsRemainingStock;
                if ($appliedPromo->items->isNotEmpty()) {
                    $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                        return $i->product_id === $product->id && is_null($i->product_variant_id);
                    });
                }
            }
        }

        // 2. If Flash Sale didn't apply, check if Bundling is active (skips general promo)
        if (! $appliedPromo && $isBundlingActive) {
            $appliedPromo = null;
        }

        // 3. Try Promo Produk
        if (! $appliedPromo && ! $isBundlingActive && $promoProduk) {
            $minQty = $promoProduk->settings['min_qty'] ?? 1;
            $pItem = null;
            if ($promoProduk->items->isNotEmpty()) {
                $pItem = $promoProduk->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
            }

            $resolvedDiscountType = $pItem ? ($pItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
            $resolvedDiscountValue = $pItem ? ($pItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
            $resolvedPromoPrice = $pItem?->promo_price;

            $ppRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, null);

            $product->promo_rule = [
                'id' => $promoProduk->id,
                'name' => $promoProduk->name,
                'type' => $promoProduk->type,
                'min_qty' => (int) $minQty,
                'discount_type' => $resolvedDiscountType,
                'discount_value' => $resolvedDiscountValue,
                'promo_price' => $resolvedPromoPrice ? (float) $resolvedPromoPrice : null,
                'remaining_promo_stock' => $ppRemainingStock,
            ];

            if ($quantity >= $minQty && (is_null($ppRemainingStock) || $ppRemainingStock > 0)) {
                $appliedPromo = $promoProduk;
                $appliedItem = $pItem;
                $remainingStock = $ppRemainingStock;
            }
        }

        // 4. Try Promo Toko / Special Deals
        if (! $appliedPromo && ! $isBundlingActive && $promoToko) {
            $appliedPromo = $promoToko;
            if ($appliedPromo->items->isNotEmpty()) {
                $appliedItem = $appliedPromo->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
            }
        }

        if ($appliedPromo) {
            $discountType = $appliedItem ? ($appliedItem->discount_type ?? $appliedPromo->discount_type) : $appliedPromo->discount_type;
            $discountValue = $appliedItem ? ($appliedItem->discount_value ?? $appliedPromo->discount_value) : $appliedPromo->discount_value;
            $promoPrice = $appliedItem?->promo_price;

            // Calculate direct promo unit price
            if ($promoPrice && $promoPrice > 0) {
                $rawPromoPrice = (float) $promoPrice;
            } else {
                if ($discountType === 'percentage') {
                    $rawPromoPrice = $basePrice - ($basePrice * ($discountValue / 100));
                } elseif ($discountType === 'fixed') {
                    $rawPromoPrice = $basePrice - $discountValue;
                } else {
                    $rawPromoPrice = $basePrice;
                }
            }
            $rawPromoPrice = max(0, $rawPromoPrice);

            // Handle Promo Stock & Split Pricing
            $promoQtyUsed = $quantity;
            $finalPrice = $rawPromoPrice;

            if (isset($remainingStock) && ! is_null($remainingStock)) {
                if ($remainingStock <= 0) {
                    // Fully exhausted, revert to normal
                    $appliedPromo = null;
                    $finalPrice = $basePrice;
                    $promoQtyUsed = 0;
                } elseif ($remainingStock < $quantity) {
                    // Split pricing!
                    $promoQtyUsed = $remainingStock;
                    $normalQty = $quantity - $promoQtyUsed;
                    $finalPrice = (($promoQtyUsed * $rawPromoPrice) + ($normalQty * $basePrice)) / $quantity;
                }
            }

            if ($appliedPromo && $finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
                $product->promo_type = $appliedPromo->type;
                $product->promo_end_time = $appliedPromo->end_time?->toIso8601String();
                $product->keep_tier_prices = $appliedPromo->settings['keep_tier_prices'] ?? false;
                $product->applied_promotion_id = $appliedPromo->id;
                $product->promo_quantity_used = $promoQtyUsed;
                if ($basePrice > 0) {
                    $product->discount_percentage = round((($basePrice - $finalPrice) / $basePrice) * 100);
                } else {
                    $product->discount_percentage = 0;
                }
            } else {
                $product->is_promo = false;
                $product->promo_price = $basePrice;
                $product->original_price = $basePrice;
                $product->discount_percentage = 0;
                $product->promo_type = null;
                $product->promo_end_time = null;
                $product->keep_tier_prices = false;
            }
        } else {
            $product->is_promo = false;
            $product->promo_price = $basePrice;
            $product->original_price = $basePrice;
            $product->discount_percentage = 0;
            $product->promo_type = null;
            $product->promo_end_time = null;
            $product->keep_tier_prices = false;
        }

        // Apply to variants if loaded
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $vPrice = (float) ($variant->productPrice?->price ?? 0);
                $vAppliedPromo = null;
                $vAppliedItem = null;
                $variant->promo_rule = null;
                $variant->applied_promotion_id = null;
                $variant->promo_quantity_used = null;
                $vRemainingStock = null;

                // 1. Try Flash Sale
                if ($flashSalePromo) {
                    $fsRemainingStock = $this->getRemainingPromoStock($flashSalePromo->id, $product->id, $variant->id);
                    if (! is_null($fsRemainingStock)) {
                        $variant->promo_rule = [
                            'id' => $flashSalePromo->id,
                            'name' => $flashSalePromo->name,
                            'type' => $flashSalePromo->type,
                            'min_qty' => 1,
                            'remaining_promo_stock' => $fsRemainingStock,
                        ];
                    }

                    if (is_null($fsRemainingStock) || $fsRemainingStock > 0) {
                        $vAppliedPromo = $flashSalePromo;
                        $vRemainingStock = $fsRemainingStock;
                        if ($vAppliedPromo->items->isNotEmpty()) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                                return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                            });
                            if (! $vAppliedItem) {
                                $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                                });
                            }
                        }
                    }
                }

                // 2. If Flash Sale didn't apply, check if Bundling is active
                if (! $vAppliedPromo && $isBundlingActive) {
                    $vAppliedPromo = null;
                }

                // 3. Try Promo Produk
                if (! $vAppliedPromo && ! $isBundlingActive && $promoProduk) {
                    $minQty = $promoProduk->settings['min_qty'] ?? 1;
                    $ppRemainingStock = $this->getRemainingPromoStock($promoProduk->id, $product->id, $variant->id);
                    $vItem = null;
                    if ($promoProduk->items->isNotEmpty()) {
                        $vItem = $promoProduk->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $vItem) {
                            $vItem = $promoProduk->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                    }

                    $vResolvedDiscountType = $vItem ? ($vItem->discount_type ?? $promoProduk->discount_type) : $promoProduk->discount_type;
                    $vResolvedDiscountValue = $vItem ? ($vItem->discount_value ?? $promoProduk->discount_value) : $promoProduk->discount_value;
                    $vResolvedPromoPrice = $vItem?->promo_price;

                    $variant->promo_rule = [
                        'id' => $promoProduk->id,
                        'name' => $promoProduk->name,
                        'type' => $promoProduk->type,
                        'min_qty' => (int) $minQty,
                        'discount_type' => $vResolvedDiscountType,
                        'discount_value' => $vResolvedDiscountValue,
                        'promo_price' => $vResolvedPromoPrice ? (float) $vResolvedPromoPrice : null,
                        'remaining_promo_stock' => $ppRemainingStock,
                    ];

                    if ($quantity >= $minQty && (is_null($ppRemainingStock) || $ppRemainingStock > 0)) {
                        $vAppliedPromo = $promoProduk;
                        $vAppliedItem = $vItem;
                        $vRemainingStock = $ppRemainingStock;
                    }
                }

                // 4. Try Promo Toko / Special Deals
                if (! $vAppliedPromo && ! $isBundlingActive && $promoToko) {
                    $vAppliedPromo = $promoToko;
                    if ($vAppliedPromo->items->isNotEmpty()) {
                        $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $vAppliedItem) {
                            $vAppliedItem = $vAppliedPromo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vRawPromoPrice = (float) $vPromoPrice;
                    } else {
                        if ($vDiscountType === 'percentage') {
                            $vRawPromoPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                        } elseif ($vDiscountType === 'fixed') {
                            $vRawPromoPrice = $vPrice - $vDiscountValue;
                        } else {
                            $vRawPromoPrice = $vPrice;
                        }
                    }
                    $vRawPromoPrice = max(0, $vRawPromoPrice);

                    $vPromoQtyUsed = $quantity;
                    $vFinalPrice = $vRawPromoPrice;

                    if (isset($vRemainingStock) && ! is_null($vRemainingStock)) {
                        if ($vRemainingStock <= 0) {
                            $vAppliedPromo = null;
                            $vFinalPrice = $vPrice;
                            $vPromoQtyUsed = 0;
                        } elseif ($vRemainingStock < $quantity) {
                            $vPromoQtyUsed = $vRemainingStock;
                            $vNormalQty = $quantity - $vPromoQtyUsed;
                            $vFinalPrice = (($vPromoQtyUsed * $vRawPromoPrice) + ($vNormalQty * $vPrice)) / $quantity;
                        }
                    }

                    if ($vAppliedPromo && $vFinalPrice < $vPrice) {
                        $variant->is_promo = true;
                        $variant->promo_price = $vFinalPrice;
                        $variant->original_price = $vPrice;
                        $variant->promo_type = $vAppliedPromo->type;
                        $variant->promo_end_time = $vAppliedPromo->end_time?->toIso8601String();
                        $variant->keep_tier_prices = $vAppliedPromo->settings['keep_tier_prices'] ?? false;
                        $variant->applied_promotion_id = $vAppliedPromo->id;
                        $variant->promo_quantity_used = $vPromoQtyUsed;
                        if ($vPrice > 0) {
                            $variant->discount_percentage = round((($vPrice - $vFinalPrice) / $vPrice) * 100);
                        } else {
                            $variant->discount_percentage = 0;
                        }
                    } else {
                        $variant->is_promo = false;
                        $variant->promo_price = $vPrice;
                        $variant->original_price = $vPrice;
                        $variant->discount_percentage = 0;
                        $variant->promo_type = null;
                        $variant->promo_end_time = null;
                        $variant->keep_tier_prices = false;
                    }
                } else {
                    $variant->is_promo = false;
                    $variant->promo_price = $vPrice;
                    $variant->original_price = $vPrice;
                    $variant->discount_percentage = 0;
                    $variant->promo_type = null;
                    $variant->promo_end_time = null;
                    $variant->keep_tier_prices = false;
                }
            }

            // Aggregate: find the cheapest variant to display on the base product card
            if ($product->variants->isNotEmpty()) {
                $promoVariants = $product->variants->filter(function ($v) {
                    return $v->is_promo;
                });

                if ($promoVariants->isNotEmpty()) {
                    // Find the cheapest promo variant by promo_price
                    $cheapestPromoVariant = $promoVariants->sortBy('promo_price')->first();

                    $product->is_promo = true;
                    $product->promo_price = $cheapestPromoVariant->promo_price;
                    $product->original_price = $cheapestPromoVariant->original_price;
                    $product->discount_percentage = $cheapestPromoVariant->discount_percentage;
                    $product->promo_type = $cheapestPromoVariant->promo_type;
                    $product->promo_end_time = $cheapestPromoVariant->promo_end_time;
                }
            }
        }
    }

    public function selectVouchers(Request $request)
    {
        $vouchers = $request->input('vouchers');
        $user = $request->user();

        if ($vouchers !== null) {
            Cache::put("user_{$user->id}_selected_vouchers", $vouchers, 3600);
        } else {
            Cache::forget("user_{$user->id}_selected_vouchers");
        }

        return redirect()->back();
    }

    /**
     * Get remaining promo stock for a promotion and product/variant.
     */
    private function getRemainingPromoStock($promotionId, $productId, $variantId = null): ?int
    {
        if ($this->promoItemsCache === null) {
            $this->promoItemsCache = [];

            $activePromoIds = Promotion::where('is_active', true)
                ->where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->pluck('id');

            $promoItems = PromotionItem::whereIn('promotion_id', $activePromoIds)->get();
            foreach ($promoItems as $item) {
                $vId = $item->product_variant_id ?? 'null';
                $this->promoItemsCache["{$item->promotion_id}_{$item->product_id}_{$vId}"] = $item;
            }
        }

        if ($this->soldPromoQuantitiesCache === null) {
            $this->soldPromoQuantitiesCache = [];

            $soldQuantities = TransactionItem::selectRaw('applied_promotion_id, product_id, product_variant_id, SUM(promo_quantity_used) as total_used')
                ->whereIn('applied_promotion_id', array_keys($this->promoItemsCache ? array_flip(array_column($this->promoItemsCache, 'promotion_id')) : []))
                ->whereHas('transaction', function ($q) {
                    $q->where('status', '!=', 'batal');
                })
                ->groupBy('applied_promotion_id', 'product_id', 'product_variant_id')
                ->get();

            foreach ($soldQuantities as $item) {
                $vId = $item->product_variant_id ?? 'null';
                $this->soldPromoQuantitiesCache["{$item->applied_promotion_id}_{$item->product_id}_{$vId}"] = (int) $item->total_used;
            }
        }

        $vKey = $variantId ?? 'null';
        $cacheKey = "{$promotionId}_{$productId}_{$vKey}";
        $promoItem = $this->promoItemsCache[$cacheKey] ?? null;

        if (! $promoItem && $variantId) {
            $fallbackKey = "{$promotionId}_{$productId}_null";
            $promoItem = $this->promoItemsCache[$fallbackKey] ?? null;
        }

        if (! $promoItem || is_null($promoItem->promo_stock)) {
            return null;
        }

        $soldPromoQty = 0;
        if (is_null($promoItem->product_variant_id)) {
            $prefix = "{$promotionId}_{$productId}_";
            foreach ($this->soldPromoQuantitiesCache as $key => $usedQty) {
                if (str_starts_with($key, $prefix)) {
                    $soldPromoQty += $usedQty;
                }
            }
        } else {
            $soldPromoQty = $this->soldPromoQuantitiesCache[$cacheKey] ?? 0;
        }

        return max(0, $promoItem->promo_stock - $soldPromoQty);
    }
}
