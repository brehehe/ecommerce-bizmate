<?php

namespace App\Actions\Cart;

use App\Models\CartItem;

class UpdateCartItemAction
{
    /**
     * Update quantity, checked state, or variant with automatic duplicate merging.
     *
     * @param  array<string, mixed>  $data
     * @return array{merged: bool, item: CartItem}
     */
    public function execute(CartItem $cartItem, array $data): array
    {
        $updateData = [];

        if (array_key_exists('quantity', $data) && $data['quantity'] !== null) {
            $updateData['quantity'] = (int) $data['quantity'];
        }

        if (array_key_exists('is_checked', $data) && $data['is_checked'] !== null) {
            $updateData['is_checked'] = (bool) $data['is_checked'];
        }

        if (! empty($data['product_variant_id'])) {
            $newVariantId = $data['product_variant_id'];

            if ($cartItem->product_variant_id !== $newVariantId) {
                $existing = CartItem::where('user_id', $cartItem->user_id)
                    ->where('product_id', $cartItem->product_id)
                    ->where('product_variant_id', $newVariantId)
                    ->where('id', '!=', $cartItem->id)
                    ->first();

                if ($existing) {
                    $existing->quantity += $cartItem->quantity;
                    $existing->is_checked = $existing->is_checked || ($updateData['is_checked'] ?? $cartItem->is_checked);
                    $existing->save();
                    $cartItem->delete();

                    return ['merged' => true, 'item' => $existing];
                }

                $updateData['product_variant_id'] = $newVariantId;
            }
        }

        $cartItem->update($updateData);

        return ['merged' => false, 'item' => $cartItem];
    }
}
