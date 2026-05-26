<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends Controller
{
    /**
     * Show the cart page.
     */
    public function index(Request $request)
    {
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

        // Calculate pricing dynamically for each cart item (applying any promotions or tier prices)
        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            // Apply promotions first
            $this->applyPromotionsToProduct($product, $activePromotions);

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

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Cart', [
            'cartItems' => $cartItems,
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
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
        ]);

        $userId = $request->user()->id;
        $productId = $request->input('product_id');
        $variantId = $request->input('product_variant_id');
        $quantity = (int) $request->input('quantity');

        // Check if this combination already exists in the cart
        $cartItem = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            CartItem::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
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
        ]);

        if ($cartItem->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = [];
        if ($request->has('quantity')) {
            $data['quantity'] = $request->input('quantity');
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
                    $existing->save();
                    $cartItem->delete();

                    return redirect()->back()->with('success', 'Keranjang digabungkan karena produk sejenis sudah ada!');
                }

                $data['product_variant_id'] = $newVariantId;
            }
        }

        $cartItem->update($data);

        return redirect()->back()->with('success', 'Keranjang berhasil diperbarui!');
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
    private function applyPromotionsToProduct(Product $product, $activePromotions): void
    {
        $basePrice = (float) ($product->productPrice?->price ?? 0);
        $appliedPromo = null;
        $appliedItem = null;

        // Search for matching promotions
        foreach ($activePromotions as $promo) {
            if ($promo->items->isEmpty()) {
                // Scope: all store products
                if (! $appliedPromo) {
                    $appliedPromo = $promo;
                }
            } else {
                // Scope: specific products
                $item = $promo->items->first(function ($i) use ($product) {
                    return $i->product_id === $product->id && is_null($i->product_variant_id);
                });
                if ($item) {
                    $appliedPromo = $promo;
                    $appliedItem = $item;
                    break;
                }
            }
        }

        if ($appliedPromo) {
            $discountType = $appliedItem ? ($appliedItem->discount_type ?? $appliedPromo->discount_type) : $appliedPromo->discount_type;
            $discountValue = $appliedItem ? ($appliedItem->discount_value ?? $appliedPromo->discount_value) : $appliedPromo->discount_value;
            $promoPrice = $appliedItem?->promo_price;

            if ($promoPrice && $promoPrice > 0) {
                $finalPrice = (float) $promoPrice;
            } else {
                if ($discountType === 'percentage') {
                    $finalPrice = $basePrice - ($basePrice * ($discountValue / 100));
                } elseif ($discountType === 'fixed') {
                    $finalPrice = $basePrice - $discountValue;
                } else {
                    $finalPrice = $basePrice;
                }
            }

            $finalPrice = max(0, $finalPrice);

            if ($finalPrice < $basePrice) {
                $product->is_promo = true;
                $product->promo_price = $finalPrice;
                $product->original_price = $basePrice;
                $product->promo_type = $appliedPromo->type;
                $product->promo_end_time = $appliedPromo->end_time?->toIso8601String();
                $product->keep_tier_prices = $appliedPromo->settings['keep_tier_prices'] ?? false;
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

                foreach ($activePromotions as $promo) {
                    if ($promo->items->isEmpty()) {
                        if (! $vAppliedPromo) {
                            $vAppliedPromo = $promo;
                        }
                    } else {
                        // Check if specific variant matches
                        $item = $promo->items->first(function ($i) use ($product, $variant) {
                            return $i->product_id === $product->id && $i->product_variant_id === $variant->id;
                        });
                        if (! $item) {
                            $item = $promo->items->first(function ($i) use ($product) {
                                return $i->product_id === $product->id && is_null($i->product_variant_id);
                            });
                        }
                        if ($item) {
                            $vAppliedPromo = $promo;
                            $vAppliedItem = $item;
                            break;
                        }
                    }
                }

                if ($vAppliedPromo) {
                    $vDiscountType = $vAppliedItem ? ($vAppliedItem->discount_type ?? $vAppliedPromo->discount_type) : $vAppliedPromo->discount_type;
                    $vDiscountValue = $vAppliedItem ? ($vAppliedItem->discount_value ?? $vAppliedPromo->discount_value) : $vAppliedPromo->discount_value;
                    $vPromoPrice = $vAppliedItem?->promo_price;

                    if ($vPromoPrice && $vPromoPrice > 0) {
                        $vFinalPrice = (float) $vPromoPrice;
                    } else {
                        if ($vDiscountType === 'percentage') {
                            $vFinalPrice = $vPrice - ($vPrice * ($vDiscountValue / 100));
                        } elseif ($vDiscountType === 'fixed') {
                            $vFinalPrice = $vPrice - $vDiscountValue;
                        } else {
                            $vFinalPrice = $vPrice;
                        }
                    }

                    $vFinalPrice = max(0, $vFinalPrice);

                    if ($vFinalPrice < $vPrice) {
                        $variant->is_promo = true;
                        $variant->promo_price = $vFinalPrice;
                        $variant->original_price = $vPrice;
                        $variant->promo_type = $vAppliedPromo->type;
                        $variant->promo_end_time = $vAppliedPromo->end_time?->toIso8601String();
                        $variant->keep_tier_prices = $vAppliedPromo->settings['keep_tier_prices'] ?? false;
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
}
