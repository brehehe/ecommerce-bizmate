<?php

namespace App\Actions\Cart;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestoProfile;
use App\Models\User;

class AddToCartAction
{
    /**
     * Add product or variant to cart with stock validation.
     *
     * @return array{success: bool, message?: string, item?: CartItem, buy_now?: bool, cart_was_reset?: bool}
     */
    public function execute(?User $user, int|string $productId, int|string|null $variantId, int $quantity, bool $buyNow = false, ?string $note = null, ?array $selectedModifiers = null): array
    {
        $product = Product::with(['productStock', 'variants.productStock'])->findOrFail($productId);
        $variant = $variantId ? ProductVariant::with('productStock')->findOrFail($variantId) : null;

        // Validasi Status Buka/Tutup Resto
        $isFood = Product::isFoodPickEnabled();
        if ($isFood) {
            $resto = RestoProfile::where('user_id', $product->user_id)->first();
            if ($resto && $resto->is_open_now === false) {
                return [
                    'success' => false,
                    'message' => 'Resto "'.($resto->resto_name ?: 'ini').'" sedang tutup sementara dan tidak dapat menerima pesanan saat ini.',
                ];
            }
        }

        // Penegakan Aturan FoodPick: 1 Resto per Cart & Reset Otomatis jika berbeda resto
        $cartWasReset = false;
        if ($isFood && ! $buyNow) {
            $newRestoId = $product->user_id;

            if ($user) {
                $existingItems = CartItem::with('product')->where('user_id', $user->id)->get();
                $hasDifferentResto = $existingItems->contains(function ($item) use ($newRestoId) {
                    return $item->product && $item->product->user_id !== $newRestoId;
                });

                if ($hasDifferentResto) {
                    CartItem::where('user_id', $user->id)->delete();
                    $cartWasReset = true;
                }
            } else {
                $guestCart = session('guest_cart', []);
                if (! empty($guestCart)) {
                    $productIds = collect($guestCart)->pluck('product_id')->filter()->unique();
                    $hasDifferentResto = Product::whereIn('id', $productIds)
                        ->where('user_id', '!=', $newRestoId)
                        ->exists();

                    if ($hasDifferentResto) {
                        session(['guest_cart' => []]);
                        $cartWasReset = true;
                    }
                }
            }
        }

        $stockRecord = $variant ? $variant->productStock : $product->productStock;
        $productLabel = $variant ? "{$product->name} (".($variant->sku ?: 'Varian').')' : $product->name;
        $hasCustomization = ! empty($note) || ! empty($selectedModifiers);
        $modifierHash = $hasCustomization ? md5(($note ?? '').serialize($selectedModifiers ?? [])) : '';
        $cartKey = "{$productId}_".($variantId ?? '0').($modifierHash ? "_{$modifierHash}" : '');

        if ($stockRecord && ! $stockRecord->is_unlimited) {
            $existingQty = 0;
            if (! $buyNow) {
                if ($user) {
                    $itemQuery = CartItem::where('user_id', $user->id)
                        ->where('product_id', $productId)
                        ->where('product_variant_id', $variantId);
                    if ($hasCustomization) {
                        $itemQuery->where('note', $note);
                    } else {
                        $itemQuery->where(function ($q) {
                            $q->whereNull('note')->orWhere('note', '');
                        });
                    }
                    $existingItem = $itemQuery->first();
                    $existingQty = $existingItem ? $existingItem->quantity : 0;
                } else {
                    $guestCart = session('guest_cart', []);
                    $existingQty = isset($guestCart[$cartKey]) ? (int) $guestCart[$cartKey]['quantity'] : 0;
                }
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
                    'note' => $note,
                    'selected_modifiers' => $selectedModifiers,
                ],
            ]);

            return ['success' => true, 'buy_now' => true];
        }

        if (! $user) {
            $guestCart = session('guest_cart', []);
            if (isset($guestCart[$cartKey])) {
                $guestCart[$cartKey]['quantity'] += $quantity;
                if ($note) {
                    $guestCart[$cartKey]['note'] = $note;
                }
                if (! empty($selectedModifiers)) {
                    $guestCart[$cartKey]['selected_modifiers'] = $selectedModifiers;
                }
            } else {
                $guestCart[$cartKey] = [
                    'id' => 'guest-'.uniqid(),
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'is_checked' => true,
                    'note' => $note,
                    'selected_modifiers' => $selectedModifiers,
                ];
            }
            session(['guest_cart' => $guestCart]);

            return ['success' => true, 'buy_now' => false, 'cart_was_reset' => $cartWasReset];
        }

        $cartItemQuery = CartItem::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        if ($hasCustomization) {
            $cartItemQuery->where('note', $note);
        } else {
            $cartItemQuery->where(function ($q) {
                $q->whereNull('note')->orWhere('note', '');
            });
        }

        $cartItem = $cartItemQuery->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->is_checked = true;
            if ($note) {
                $cartItem->note = $note;
            }
            if (! empty($selectedModifiers)) {
                $cartItem->selected_modifiers = $selectedModifiers;
            }
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'is_checked' => true,
                'note' => $note,
                'selected_modifiers' => $selectedModifiers,
            ]);
        }

        return ['success' => true, 'item' => $cartItem, 'buy_now' => false, 'cart_was_reset' => $cartWasReset];
    }
}
