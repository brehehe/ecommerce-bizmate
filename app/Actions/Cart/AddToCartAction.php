<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class AddToCartAction
{
    /**
     * Add product or variant to cart with stock validation.
     *
     * @return array{success: bool, message?: string, item?: CartItem, buy_now?: bool}
     */
    public function execute(User $user, int|string $productId, int|string|null $variantId, int $quantity, bool $buyNow = false): array
    {
        $product = Product::with(['productStock', 'variants.productStock'])->findOrFail($productId);
        $variant = $variantId ? ProductVariant::with('productStock')->findOrFail($variantId) : null;

        $stockRecord = $variant ? $variant->productStock : $product->productStock;
        $productLabel = $variant ? "{$product->name} (".($variant->sku ?: 'Varian').')' : $product->name;

        if ($stockRecord && ! $stockRecord->is_unlimited) {
            $existingQty = 0;
            if (! $buyNow) {
                $existingItem = CartItem::where('user_id', $user->id)
                    ->where('product_id', $productId)
                    ->where('product_variant_id', $variantId)
                    ->first();
                $existingQty = $existingItem ? $existingItem->quantity : 0;
            }

            $totalRequestedQty = $existingQty + $quantity;

            if ($stockRecord->stock < $totalRequestedQty) {
                if ($buyNow) {
                    return ['success' => false, 'message' => "Stok {$productLabel} tidak mencukupi untuk jumlah yang Anda beli."];
                }

                $available = max(0, $stockRecord->stock - $existingQty);
                if ($available <= 0) {
                    return ['success' => false, 'message' => "Stok {$productLabel} sudah mencapai batas maksimal di keranjang Anda."];
                }

                return ['success' => false, 'message' => "Stok {$productLabel} tidak mencukupi. Anda bisa menambah {$available} pcs lagi."];
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

            return ['success' => true, 'buy_now' => true];
        }

        $cartItem = CartItem::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->is_checked = true;
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'is_checked' => true,
            ]);
        }

        return ['success' => true, 'item' => $cartItem, 'buy_now' => false];
    }
}
